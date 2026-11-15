<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Account;
use App\Identity\Infrastructure\Persistence\DoctrineAccountRepository;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[Group('database')]
final class DoctrineAccountRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineAccountRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineAccountRepository($entityManager);
    }

    #[Test]
    public function it_finds_the_account_of_a_member_with_its_password_hash_and_last_login(): void
    {
        $memberId = Uuid::v7();
        $account = Account::open($memberId);
        $account->changePasswordHash('$2y$13$abcdefghijklmnopqrstuuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ012');
        $account->recordLogin(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));
        $this->repository->add($account);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->repository->ofMember($memberId);

        $this->assertSame(
            [$account->id()->toRfc4122(), '$2y$13$abcdefghijklmnopqrstuuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ012', '2026-11-15T12:00:00+00:00'],
            [$read?->id()->toRfc4122(), $read?->passwordHash(), $read?->lastLoginAt()?->format(\DATE_ATOM)],
        );
    }

    #[Test]
    public function it_finds_no_account_for_a_member_without_one(): void
    {
        $this->assertNull($this->repository->ofMember(Uuid::v7()));
    }
}
