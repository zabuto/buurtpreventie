<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Invitation;
use App\Identity\Domain\InvitationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineInvitationRepository implements InvitationRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function add(#[SensitiveParameter] Invitation $invitation): void
    {
        $this->entityManager->persist($invitation);
    }

    #[Override]
    public function bySecretHashForUpdate(#[SensitiveParameter] string $secretHash): ?Invitation
    {
        /** @var Invitation|null */
        return $this->entityManager
            ->createQuery(\sprintf('SELECT i FROM %s i WHERE i.secretHash = :hash', Invitation::class))
            ->setParameter('hash', $secretHash, 'binary')
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult()
        ;
    }

    #[Override]
    public function openOfMember(Uuid $memberId, DateTimeImmutable $now): array
    {
        /** @var list<Invitation> */
        return $this->entityManager
            ->createQuery(\sprintf(
                'SELECT i FROM %s i WHERE i.memberId = :member AND i.usedAt IS NULL AND i.expiresAt > :now',
                Invitation::class,
            ))
            ->setParameter('member', $memberId, 'uuid')
            ->setParameter('now', $now, 'utc_datetime_immutable')
            ->getResult()
        ;
    }
}
