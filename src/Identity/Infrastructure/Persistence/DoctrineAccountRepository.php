<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Account;
use App\Identity\Domain\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAccountRepository implements AccountRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function ofMember(Uuid $memberId): ?Account
    {
        return $this->entityManager->getRepository(Account::class)->findOneBy(['memberId' => $memberId]);
    }

    #[Override]
    public function add(Account $account): void
    {
        $this->entityManager->persist($account);
    }
}
