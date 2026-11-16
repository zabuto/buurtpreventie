<?php

declare(strict_types=1);

namespace App\Tests\Audit\Infrastructure\Persistence;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditFilter;
use App\Audit\Infrastructure\Persistence\DoctrineAuditEntryRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The query behind the audit log of the admin. Each test gives its entries an action of its own, so rows of other
 * tests in the same database never match.
 */
#[Group('database')]
final class DoctrineAuditEntryMatchingTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineAuditEntryRepository $repository;
    private string $action;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineAuditEntryRepository($entityManager);
        $this->action = 'test.' . \bin2hex(\random_bytes(6));
    }

    #[Test]
    public function it_finds_what_a_member_did_and_what_was_done_to_the_member_newest_first(): void
    {
        $jan = Uuid::v7();
        $byJan = $this->stored('round', Uuid::v7(), $jan, '2026-11-13T12:00:00+00:00');
        $aboutJan = $this->stored('member', $jan, Uuid::v7(), '2026-11-14T12:00:00+00:00');
        $this->stored('round', $jan, Uuid::v7(), '2026-11-15T12:00:00+00:00');
        $this->flushAndForget();

        $found = $this->repository->matching(new AuditFilter(member: $jan), 0, 10);

        $this->assertSame(self::ids($aboutJan, $byJan), self::ids(...$found));
    }

    #[Test]
    public function it_finds_one_action_in_a_period(): void
    {
        $inside = $this->stored('round', Uuid::v7(), Uuid::v7(), '2026-11-14T12:00:00+00:00');
        $this->stored('round', Uuid::v7(), Uuid::v7(), '2026-11-15T12:00:00+00:00');
        $this->stored('round', Uuid::v7(), Uuid::v7(), '2026-11-13T11:59:59+00:00');
        $this->flushAndForget();

        $found = $this->repository->matching(
            new AuditFilter(
                action: $this->action,
                from: new DateTimeImmutable('2026-11-13T12:00:00+00:00'),
                to: new DateTimeImmutable('2026-11-15T12:00:00+00:00'),
            ),
            0,
            10,
        );

        $this->assertSame(self::ids($inside), self::ids(...$found));
    }

    #[Test]
    public function it_counts_the_entries_that_match_without_a_page(): void
    {
        $jan = Uuid::v7();
        $this->stored('round', Uuid::v7(), $jan, '2026-11-13T12:00:00+00:00');
        $this->stored('member', $jan, Uuid::v7(), '2026-11-14T12:00:00+00:00');
        $this->stored('round', Uuid::v7(), Uuid::v7(), '2026-11-14T12:00:00+00:00');
        $this->stored('round', $jan, Uuid::v7(), '2026-11-15T12:00:00+00:00');
        $this->flushAndForget();

        $this->assertSame([2, 3, 4], [
            $this->repository->countMatching(new AuditFilter(member: $jan)),
            $this->repository->countMatching(new AuditFilter(action: $this->action, from: new DateTimeImmutable('2026-11-14T00:00:00+00:00'))),
            $this->repository->countMatching(new AuditFilter(action: $this->action)),
        ]);
    }

    #[Test]
    public function it_gives_one_page_at_a_time(): void
    {
        $this->stored('round', Uuid::v7(), Uuid::v7(), '2026-11-15T12:00:00+00:00');
        $middle = $this->stored('round', Uuid::v7(), Uuid::v7(), '2026-11-14T12:00:00+00:00');
        $this->stored('round', Uuid::v7(), Uuid::v7(), '2026-11-13T12:00:00+00:00');
        $this->flushAndForget();

        $found = $this->repository->matching(new AuditFilter(action: $this->action), 1, 1);

        $this->assertSame(self::ids($middle), self::ids(...$found));
    }

    private function stored(string $subjectType, Uuid $subjectId, Uuid $actorId, string $at): AuditEntry
    {
        $entry = AuditEntry::of(new AuditedAction($this->action, $subjectType, $subjectId), $actorId, 'Jan B.', Channel::Web, new DateTimeImmutable($at));
        $this->entityManager->persist($entry);

        return $entry;
    }

    private function flushAndForget(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @return list<string>
     */
    private static function ids(AuditEntry ...$entries): array
    {
        return \array_values(\array_map(static fn(AuditEntry $entry): string => $entry->id()->toRfc4122(), $entries));
    }
}
