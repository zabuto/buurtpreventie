<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Invitation;
use App\Identity\Infrastructure\Persistence\DoctrineInvitationRepository;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[Group('database')]
final class DoctrineInvitationRepositoryTest extends KernelTestCase
{
    private const string NOW = '2026-11-16T10:00:00+00:00';

    private EntityManagerInterface $entityManager;
    private DoctrineInvitationRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineInvitationRepository($entityManager);
    }

    #[Test]
    public function it_finds_an_invitation_by_the_hash_of_its_secret(): void
    {
        $secret = 'bpi_' . \bin2hex(\random_bytes(32));
        $invitation = $this->stored(Uuid::v7(), $secret);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->wrapInTransaction(fn(): ?Invitation => $this->repository->bySecretHashForUpdate(Invitation::hash($secret)));

        $this->assertEquals($invitation->id(), $found?->id());
    }

    #[Test]
    public function it_finds_no_invitation_for_an_unknown_secret(): void
    {
        $unknown = Invitation::hash('bpi_' . \bin2hex(\random_bytes(32)));

        $this->assertNull($this->entityManager->wrapInTransaction(fn(): ?Invitation => $this->repository->bySecretHashForUpdate($unknown)));
    }

    #[Test]
    public function it_finds_only_the_invitations_of_the_member_that_can_still_be_accepted(): void
    {
        $memberId = Uuid::v7();
        $open = $this->stored($memberId);
        $this->stored($memberId)->accept(new DateTimeImmutable(self::NOW));
        $this->stored($memberId, createdAt: new DateTimeImmutable('2026-11-14T10:00:00+00:00'));
        $this->stored(Uuid::v7());
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->repository->openOfMember($memberId, new DateTimeImmutable(self::NOW));

        $this->assertEquals([$open->id()], \array_map(static fn(Invitation $invitation): Uuid => $invitation->id(), $found));
    }

    private function stored(
        Uuid $memberId,
        #[SensitiveParameter]
        ?string $secret = null,
        DateTimeImmutable $createdAt = new DateTimeImmutable('2026-11-16T09:00:00+00:00'),
    ): Invitation {
        $invitation = Invitation::issue($memberId, $secret ?? 'bpi_' . \bin2hex(\random_bytes(32)), $createdAt);
        $this->repository->add($invitation);

        return $invitation;
    }
}
