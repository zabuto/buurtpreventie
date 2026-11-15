<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Persistence;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAuditEntryRepository implements AuditEntryRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * One bulk update: a member may have thousands of entries, and loading each would cost as many objects.
     */
    #[Override]
    public function eraseActorName(Uuid $memberId): void
    {
        $this->entityManager
            ->createQuery(\sprintf('UPDATE %s e SET e.actorName = NULL WHERE e.actorId = :member', AuditEntry::class))
            ->setParameter('member', $memberId, 'uuid')
            ->execute()
        ;
    }
}
