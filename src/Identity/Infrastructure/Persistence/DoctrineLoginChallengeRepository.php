<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\LoginChallenge;
use App\Identity\Domain\LoginChallengeRepository;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineLoginChallengeRepository implements LoginChallengeRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function add(LoginChallenge $challenge): void
    {
        $this->entityManager->persist($challenge);
    }

    #[Override]
    public function forUpdate(string $id): ?LoginChallenge
    {
        return $this->entityManager->find(LoginChallenge::class, $id, LockMode::PESSIMISTIC_WRITE);
    }

    #[Override]
    public function openOfMember(Uuid $memberId, DateTimeImmutable $now): array
    {
        /** @var list<LoginChallenge> */
        return $this->entityManager
            ->createQuery(\sprintf(
                'SELECT c FROM %s c WHERE c.memberId = :member AND c.usedAt IS NULL AND c.expiresAt > :now AND c.attempts < :maximum',
                LoginChallenge::class,
            ))
            ->setParameter('member', $memberId, 'uuid')
            ->setParameter('now', $now, 'utc_datetime_immutable')
            ->setParameter('maximum', LoginChallenge::MAXIMUM_ATTEMPTS)
            ->getResult()
        ;
    }
}
