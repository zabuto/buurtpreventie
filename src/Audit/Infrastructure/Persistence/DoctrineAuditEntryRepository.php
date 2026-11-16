<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Persistence;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditEntryRepository;
use App\Audit\Domain\AuditFilter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
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

    #[Override]
    public function matching(AuditFilter $filter, int $offset, int $limit): array
    {
        $query = $this
            ->filtered($filter)
            ->select('e')
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
        ;

        /** @var list<AuditEntry> */
        return $query->getQuery()->getResult();
    }

    #[Override]
    public function countMatching(AuditFilter $filter): int
    {
        return (int) $this->filtered($filter)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();
    }

    private function filtered(AuditFilter $filter): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()->from(AuditEntry::class, 'e');
        if (null !== $filter->member) {
            $query
                ->andWhere("e.actorId = :member OR (e.subjectType = 'member' AND e.subjectId = :member)")
                ->setParameter('member', $filter->member, 'uuid')
            ;
        }

        if (null !== $filter->action) {
            $query->andWhere('e.action = :action')->setParameter('action', $filter->action);
        }

        if (null !== $filter->from) {
            $query->andWhere('e.occurredAt >= :from')->setParameter('from', $filter->from, 'utc_datetime_immutable');
        }

        if (null !== $filter->to) {
            $query->andWhere('e.occurredAt < :to')->setParameter('to', $filter->to, 'utc_datetime_immutable');
        }

        return $query;
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
