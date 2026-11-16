<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Persistence;

use App\Identity\Domain\CodeAttempt;
use App\Identity\Domain\LoginChallenge;
use App\Identity\Infrastructure\Persistence\DoctrineLoginChallengeRepository;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[Group('database')]
final class DoctrineLoginChallengeRepositoryTest extends KernelTestCase
{
    private const string NOW = '2026-11-16T10:00:00+00:00';

    private EntityManagerInterface $entityManager;
    private DoctrineLoginChallengeRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineLoginChallengeRepository($entityManager);
    }

    #[Test]
    public function it_brings_a_challenge_back_with_its_code_hash_and_attempts(): void
    {
        $challenge = $this->stored(Uuid::v7());
        $challenge->attempt(\str_repeat('w', 32), new DateTimeImmutable(self::NOW));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->entityManager->wrapInTransaction(fn(): ?LoginChallenge => $this->repository->forUpdate($challenge->id()));

        $this->assertNotNull($read);
        $this->assertSame([4, CodeAttempt::Accepted], [$read->attemptsLeft(), $read->attempt(\str_repeat('c', 32), new DateTimeImmutable(self::NOW))]);
    }

    #[Test]
    public function it_finds_no_challenge_for_an_unknown_id(): void
    {
        $this->assertNull($this->entityManager->wrapInTransaction(fn(): ?LoginChallenge => $this->repository->forUpdate(\bin2hex(\random_bytes(16)))));
    }

    #[Test]
    public function it_finds_only_the_challenges_of_the_member_that_still_take_a_code(): void
    {
        $memberId = Uuid::v7();
        $open = $this->stored($memberId);
        $this->stored($memberId)->attempt(\str_repeat('c', 32), new DateTimeImmutable(self::NOW));
        $this->stored($memberId, new DateTimeImmutable('2026-11-16T08:59:59+00:00'));
        $exhausted = $this->stored($memberId);
        for ($attempt = 1; $attempt <= LoginChallenge::MAXIMUM_ATTEMPTS; ++$attempt) {
            $exhausted->attempt(\str_repeat('w', 32), new DateTimeImmutable(self::NOW));
        }
        $this->stored(Uuid::v7());
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->repository->openOfMember($memberId, new DateTimeImmutable(self::NOW));

        $this->assertSame([$open->id()], \array_map(static fn(LoginChallenge $challenge): string => $challenge->id(), $found));
    }

    private function stored(Uuid $memberId, DateTimeImmutable $createdAt = new DateTimeImmutable('2026-11-16T09:30:00+00:00')): LoginChallenge
    {
        $challenge = LoginChallenge::open(\bin2hex(\random_bytes(16)), $memberId, \str_repeat('c', 32), $createdAt);
        $this->repository->add($challenge);

        return $challenge;
    }
}
