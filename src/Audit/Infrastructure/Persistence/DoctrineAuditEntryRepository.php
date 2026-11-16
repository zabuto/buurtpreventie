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

    #[Override]
    public function byActor(Uuid $memberId, int $offset, int $limit): array
    {
        /** @var list<AuditEntry> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('e')
            ->from(AuditEntry::class, 'e')
            ->where('e.actorId = :member')
            ->setParameter('member', $memberId, 'uuid')
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    #[Override]
    public function bySubject(string $subjectType, Uuid $subjectId): array
    {
        /** @var list<AuditEntry> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('e')
            ->from(AuditEntry::class, 'e')
            ->where('e.subjectId = :subject')
            ->andWhere('e.subjectType = :type')
            ->setParameter('subject', $subjectId, 'uuid')
            ->setParameter('type', $subjectType)
            ->orderBy('e.occurredAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
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
