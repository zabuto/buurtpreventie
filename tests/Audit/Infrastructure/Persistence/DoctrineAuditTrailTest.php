<?php

declare(strict_types=1);

namespace App\Tests\Audit\Infrastructure\Persistence;

use App\Audit\Domain\AuditEntry;
use App\Audit\Infrastructure\Persistence\DoctrineAuditTrail;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Doctrine\DoctrineTransaction;
use App\Tests\Database\TestDatabase;
use App\Tests\Shared\Fake\FixedExecutionContext;
use App\Tests\Shared\Fake\FixedMemberNames;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use DomainException;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * Every entry is read back after the entity manager forgot it, so the values come from the database.
 */
#[Group('database')]
final class DoctrineAuditTrailTest extends KernelTestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    private EntityManagerInterface $entityManager;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    #[Test]
    public function it_stores_the_action_with_the_time_the_actor_by_name_and_the_channel(): void
    {
        $jan = Uuid::v7();
        $round = Uuid::v7();
        $trail = $this->trail(new Actor($jan, [Role::Walker]), Channel::Api, [$jan->toRfc4122() => 'Jan B.']);

        $this->transaction()->run(static fn() => $trail->record(
            new AuditedAction('round.changed', 'round', $round, ['startsAt' => ['old' => '22:00', 'new' => '23:00']]),
        ));

        $entry = $this->readBack($round);
        $this->assertEquals(
            [new DateTimeImmutable(self::NOW), $jan, 'Jan B.', 'round.changed', Channel::Api],
            [$entry->occurredAt(), $entry->actorId(), $entry->actorName(), $entry->action(), $entry->channel()],
        );
        $this->assertSame(['startsAt' => ['old' => '22:00', 'new' => '23:00']], $entry->changes());
    }

    #[Test]
    public function it_stores_the_system_as_actor_when_no_member_acts(): void
    {
        $round = Uuid::v7();
        $trail = $this->trail(null, Channel::Cron);

        $this->transaction()->run(static fn() => $trail->record(new AuditedAction('participation.reminded', 'round', $round)));

        $entry = $this->readBack($round);
        $this->assertSame([true, null, []], [$entry->isBySystem(), $entry->actorName(), $entry->changes()]);
    }

    /**
     * The entry is part of the change it describes: rolled back together, so the log never holds an action that
     * did not happen.
     */
    #[Test]
    public function it_stores_nothing_when_the_transaction_rolls_back(): void
    {
        $round = Uuid::v7();
        $trail = $this->trail(null, Channel::Console);

        $refusal = new DomainException('The round is full.');

        $caught = null;
        try {
            $this->transaction()->run(static function () use ($trail, $round, $refusal): never {
                $trail->record(new AuditedAction('round.created', 'round', $round));

                throw $refusal;
            });
        } catch (DomainException $exception) {
            $caught = $exception;
        }

        $this->assertSame($refusal, $caught);
        $this->assertSame(
            [],
            $this->entityManager->getConnection()->fetchFirstColumn('SELECT id FROM audit_entry WHERE subject_id = ?', [$round->toBinary()]),
        );
    }

    /**
     * @param array<string, string> $names
     */
    private function trail(?Actor $actor, Channel $channel, array $names = []): DoctrineAuditTrail
    {
        return new DoctrineAuditTrail(
            $this->entityManager,
            new MockClock(self::NOW),
            new FixedExecutionContext($actor, $channel),
            new FixedMemberNames($names),
        );
    }

    private function readBack(Uuid $subjectId): AuditEntry
    {
        $this->entityManager->clear();
        $entries = $this->entityManager->getRepository(AuditEntry::class)->findBy(['subjectId' => $subjectId]);
        $this->assertCount(1, $entries);

        return $entries[0];
    }

    private function transaction(): DoctrineTransaction
    {
        $registry = self::getContainer()->get(ManagerRegistry::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(ManagerRegistry::class, $registry);

        return new DoctrineTransaction($registry);
    }
}
