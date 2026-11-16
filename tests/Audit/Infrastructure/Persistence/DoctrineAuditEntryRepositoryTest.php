<?php

declare(strict_types=1);

namespace App\Tests\Audit\Infrastructure\Persistence;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\ChainLink;
use App\Audit\Infrastructure\HmacChainHasher;
use App\Audit\Infrastructure\Persistence\DoctrineAuditEntryRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[Group('database')]
final class DoctrineAuditEntryRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        // The head is locked only inside a transaction, which the test bundle keeps out of sight of the ORM.
        $this->entityManager->getConnection()->beginTransaction();
    }

    #[Test]
    public function it_erases_the_name_of_the_member_from_every_entry_of_that_member_only(): void
    {
        $jan = Uuid::v7();
        $piet = Uuid::v7();
        $first = $this->stored($jan, 'Jan B.');
        $second = $this->stored($jan, 'Jan B.');
        $other = $this->stored($piet, 'Piet J.');
        $this->entityManager->flush();

        new DoctrineAuditEntryRepository($this->entityManager)->eraseActorName($jan);

        $this->entityManager->clear();
        $this->assertSame([[null, $jan->toRfc4122()], [null, $jan->toRfc4122()], ['Piet J.', $piet->toRfc4122()]], \array_map(
            fn(AuditEntry $entry): array => [
                $this->reread($entry)->actorName(),
                $this->reread($entry)->actorId()?->toRfc4122(),
            ],
            [$first, $second, $other],
        ));
    }

    #[Test]
    public function it_finds_the_entries_of_one_actor_newest_first_a_page_at_a_time(): void
    {
        $jan = Uuid::v7();
        $oldest = $this->stored($jan, 'Jan B.', '2026-11-13T12:00:00+00:00');
        $middle = $this->stored($jan, 'Jan B.', '2026-11-14T12:00:00+00:00');
        $this->stored($jan, 'Jan B.', '2026-11-15T12:00:00+00:00');
        $this->stored(Uuid::v7(), 'Piet J.', '2026-11-14T13:00:00+00:00');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = new DoctrineAuditEntryRepository($this->entityManager)->byActor($jan, offset: 1, limit: 5);

        $this->assertSame(
            [$middle->id()->toRfc4122(), $oldest->id()->toRfc4122()],
            \array_map(static fn(AuditEntry $entry): string => $entry->id()->toRfc4122(), $found),
        );
    }

    #[Test]
    public function it_finds_the_entries_about_one_subject_oldest_first(): void
    {
        $round = Uuid::v7();
        $later = $this->storedAbout('round', $round, '2026-11-15T12:00:00+00:00');
        $earlier = $this->storedAbout('round', $round, '2026-11-14T12:00:00+00:00');
        $this->storedAbout('member', $round, '2026-11-14T13:00:00+00:00');
        $this->storedAbout('round', Uuid::v7(), '2026-11-14T13:00:00+00:00');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = new DoctrineAuditEntryRepository($this->entityManager)->bySubject('round', $round);

        $this->assertSame(
            [$earlier->id()->toRfc4122(), $later->id()->toRfc4122()],
            \array_map(static fn(AuditEntry $entry): string => $entry->id()->toRfc4122(), $found),
        );
    }

    #[Test]
    public function it_erases_the_changes_of_every_entry_about_the_member_only(): void
    {
        $anna = Uuid::v7();
        $aboutAnna = $this->linked($this->entry('member.added', 'member', $anna, ['displayName' => ['old' => null, 'new' => 'Anna H.']]));
        $aboutOther = $this->linked($this->entry('member.added', 'member', Uuid::v7(), ['displayName' => ['old' => null, 'new' => 'Ben K.']]));
        $this->entityManager->flush();

        $this->repository()->eraseChangesAbout($anna);

        $this->entityManager->clear();
        $this->assertSame(
            [[], ['displayName' => ['old' => null, 'new' => 'Ben K.']], true],
            [$this->reread($aboutAnna)->changes(), $this->reread($aboutOther)->changes(), $this->reread($aboutAnna)->hasErasedChanges()],
        );
    }

    #[Test]
    public function it_moves_the_head_to_each_appended_entry(): void
    {
        $this->linked($this->entry('round.created', 'round', Uuid::v7()));
        $last = $this->linked($this->entry('round.changed', 'round', Uuid::v7()));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $head = $this->repository()->lockHead();

        $this->assertEquals($last->chainLink(), $head);
    }

    /**
     * Another process may move the head after this entity manager read it; the lock must read the committed row.
     */
    #[Test]
    public function it_locks_the_head_as_stored_rather_than_as_read_before(): void
    {
        $repository = $this->repository();
        $repository->head();
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE audit_chain SET chain_position = 9000, chain_hash = ? WHERE name = 'head'",
            [\str_repeat('n', 32)],
        );

        $head = $repository->lockHead();

        $this->assertEquals(new ChainLink(9000, \str_repeat('n', 32)), $head);
    }

    /**
     * Sample data empty every table, the head and anchor included.
     */
    #[Test]
    public function it_starts_the_chain_when_head_and_anchor_are_missing(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM audit_entry');
        $connection->executeStatement('DELETE FROM audit_chain');

        $head = $this->repository()->lockHead();

        $this->assertEquals(
            [ChainLink::start(), ChainLink::start(), ['head']],
            [$head, $this->repository()->anchor(), $connection->fetchFirstColumn('SELECT name FROM audit_chain')],
        );
    }

    #[Test]
    public function it_gives_the_entries_after_a_position_in_their_order_up_to_the_limit(): void
    {
        $from = $this->repository()->lockHead()->position;
        $first = $this->linked($this->entry('round.created', 'round', Uuid::v7()));
        $second = $this->linked($this->entry('round.changed', 'round', Uuid::v7()));
        $this->linked($this->entry('round.deleted', 'round', Uuid::v7()));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->repository()->chainAfter($from, 2);

        $this->assertSame(
            [$first->id()->toRfc4122(), $second->id()->toRfc4122()],
            \array_map(static fn(AuditEntry $entry): string => $entry->id()->toRfc4122(), $found),
        );
    }

    #[Test]
    public function it_counts_the_entries_without_a_hash_up_to_a_position(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM audit_entry');
        $first = $this->linked($this->entry('round.created', 'round', Uuid::v7()));
        $second = $this->linked($this->entry('round.changed', 'round', Uuid::v7()));
        $this->linked($this->entry('round.deleted', 'round', Uuid::v7()));
        $this->entityManager->flush();
        $connection->executeStatement('UPDATE audit_entry SET chain_hash = NULL');

        $count = $this->repository()->countUnchainedThrough((int) $second->chainPosition());

        $this->assertSame([2, 1], [$count, $this->repository()->countUnchainedThrough((int) $first->chainPosition())]);
    }

    #[Test]
    public function it_prunes_through_a_position_and_moves_the_anchor_to_the_last_entry_removed(): void
    {
        $first = $this->linked($this->entry('round.created', 'round', Uuid::v7()));
        $second = $this->linked($this->entry('round.changed', 'round', Uuid::v7()));
        $third = $this->linked($this->entry('round.deleted', 'round', Uuid::v7()));
        $this->entityManager->flush();

        $this->repository()->pruneThrough((int) $second->chainPosition());

        $this->entityManager->clear();
        $this->assertEquals(
            [$second->chainLink(), [$third->id()->toRfc4122()]],
            [
                $this->repository()->anchor(),
                \array_map(
                    static fn(AuditEntry $entry): string => $entry->id()->toRfc4122(),
                    $this->repository()->chainAfter((int) $first->chainPosition() - 1, 10),
                ),
            ],
        );
    }

    #[Test]
    public function it_leaves_the_anchor_when_pruning_entries_before_it(): void
    {
        $first = $this->linked($this->entry('round.created', 'round', Uuid::v7()));
        $second = $this->linked($this->entry('round.changed', 'round', Uuid::v7()));
        $this->entityManager->flush();
        $this->repository()->pruneThrough((int) $second->chainPosition());

        $this->repository()->pruneThrough((int) $first->chainPosition());

        $this->assertEquals($second->chainLink(), $this->repository()->anchor());
    }

    #[Test]
    public function it_refuses_to_prune_through_a_position_no_entry_holds(): void
    {
        $head = $this->repository()->lockHead();

        $this->expectException(LogicException::class);

        $this->repository()->pruneThrough($head->position + 1);
    }

    private function storedAbout(string $subjectType, Uuid $subjectId, string $at): AuditEntry
    {
        return $this->linked(AuditEntry::of(
            new AuditedAction('round.changed', $subjectType, $subjectId),
            Uuid::v7(),
            'Jan B.',
            Channel::Web,
            new DateTimeImmutable($at),
        ));
    }

    private function stored(Uuid $actorId, string $actorName, string $at = '2026-11-15T12:00:00+00:00'): AuditEntry
    {
        return $this->linked(AuditEntry::of(
            new AuditedAction('round.created', 'round', Uuid::v7()),
            $actorId,
            $actorName,
            Channel::Web,
            new DateTimeImmutable($at),
        ));
    }

    private function reread(AuditEntry $entry): AuditEntry
    {
        $read = $this->entityManager->find(AuditEntry::class, $entry->id());
        $this->assertNotNull($read);

        return $read;
    }

    /**
     * @param array<string, array{old: scalar|null, new: scalar|null}> $changes
     */
    private function entry(string $action, string $subjectType, Uuid $subjectId, array $changes = []): AuditEntry
    {
        return AuditEntry::of(
            new AuditedAction($action, $subjectType, $subjectId, $changes),
            Uuid::v7(),
            'Anna H.',
            Channel::Web,
            new DateTimeImmutable('2026-11-15T12:00:00+00:00'),
        );
    }

    /**
     * Links the entry after the head and stores it, as the audit trail does at the end of a transaction.
     */
    private function linked(AuditEntry $entry): AuditEntry
    {
        $repository = $this->repository();
        $entry->link($repository->lockHead(), new HmacChainHasher('audit-key'));
        $repository->append($entry);

        return $entry;
    }

    private function repository(): DoctrineAuditEntryRepository
    {
        return new DoctrineAuditEntryRepository($this->entityManager);
    }
}
