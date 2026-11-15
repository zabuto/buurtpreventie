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

    private function stored(Uuid $actorId, string $actorName): AuditEntry
    {
        $entry = AuditEntry::of(
            new AuditedAction('round.created', 'round', Uuid::v7()),
            $actorId,
            $actorName,
            Channel::Web,
            new DateTimeImmutable('2026-11-15T12:00:00+00:00'),
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
