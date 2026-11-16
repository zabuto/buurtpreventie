<?php

declare(strict_types=1);

namespace App\Tests\Audit\Infrastructure\Persistence;

use App\Audit\Domain\AuditEntry;
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

    private function stored(Uuid $actorId, string $actorName, string $at = '2026-11-15T12:00:00+00:00'): AuditEntry
    {
        $entry = AuditEntry::of(
            new AuditedAction('round.created', 'round', Uuid::v7()),
            $actorId,
            $actorName,
            Channel::Web,
            new DateTimeImmutable($at),
        );
        $this->entityManager->persist($entry);

        return $entry;
    }

    private function reread(AuditEntry $entry): AuditEntry
    {
        $read = $this->entityManager->find(AuditEntry::class, $entry->id());
        $this->assertNotNull($read);

        return $read;
    }
}
