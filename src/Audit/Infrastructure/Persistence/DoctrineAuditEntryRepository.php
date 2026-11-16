<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Persistence;

use App\Audit\Domain\AuditChainMark;
use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditEntryRepository;
use App\Audit\Domain\AuditFilter;
use App\Audit\Domain\ChainLink;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use LogicException;
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

    /**
     * One bulk update, like erasing the name.
     */
    #[Override]
    public function eraseChangesAbout(Uuid $memberId): void
    {
        $this->entityManager
            ->createQuery(\sprintf(
                "UPDATE %s e SET e.changes = NULL WHERE e.subjectType = 'member' AND e.subjectId = :member",
                AuditEntry::class,
            ))
            ->setParameter('member', $memberId, 'uuid')
            ->execute()
        ;
    }

    #[Override]
    public function lockHead(): ChainLink
    {
        return $this->lockedMark(AuditChainMark::HEAD)->link();
    }

    /**
     * Flushed at once: the next lock in the same transaction refreshes the head from the database, and would read it
     * unmoved otherwise.
     */
    #[Override]
    public function append(AuditEntry $entry): void
    {
        $link = $entry->chainLink();
        if (null === $link) {
            throw new LogicException(\sprintf('Audit entry %s is not linked to the chain.', $entry->id()));
        }

        $head = $this->entityManager->find(AuditChainMark::class, AuditChainMark::HEAD);
        if (null === $head) {
            throw new LogicException('The head of the audit chain is not locked.');
        }

        $this->entityManager->persist($entry);
        $head->moveTo($link);
        $this->entityManager->flush();
    }

    #[Override]
    public function head(): ChainLink
    {
        return $this->freshMark(AuditChainMark::HEAD);
    }

    #[Override]
    public function anchor(): ChainLink
    {
        return $this->freshMark(AuditChainMark::ANCHOR);
    }

    /**
     * Detached, so walking a long log does not keep every entry in memory.
     */
    #[Override]
    public function chainAfter(int $position, int $limit): array
    {
        /** @var list<AuditEntry> $entries */
        $entries = $this->entityManager
            ->createQueryBuilder()
            ->select('e')
            ->from(AuditEntry::class, 'e')
            ->where('e.chainPosition > :position')
            ->setParameter('position', $position)
            ->orderBy('e.chainPosition', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
        foreach ($entries as $entry) {
            $this->entityManager->detach($entry);
        }

        return $entries;
    }

    #[Override]
    public function countUnchainedThrough(int $position): int
    {
        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(AuditEntry::class, 'e')
            ->where('e.chainPosition <= :position')
            ->andWhere('e.chainHash IS NULL')
            ->setParameter('position', $position)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * The anchor is locked first, so two prunes at once move it in turn rather than past each other, and flushed at
     * once, as a later lock refreshes it from the database.
     */
    #[Override]
    public function pruneThrough(int $position): void
    {
        $anchor = $this->lockedMark(AuditChainMark::ANCHOR);

        if ($position > $anchor->link()->position) {
            $last = $this->entityManager->getRepository(AuditEntry::class)->findOneBy(['chainPosition' => $position]);
            $link = $last?->chainLink();
            if (null === $link) {
                throw new LogicException(\sprintf('No linked audit entry holds position %d.', $position));
            }

            $anchor->moveTo($link);
            $this->entityManager->flush();
        }

        $this->entityManager
            ->createQuery(\sprintf('DELETE FROM %s e WHERE e.chainPosition <= :position', AuditEntry::class))
            ->setParameter('position', $position)
            ->execute()
        ;
    }

    /**
     * A refresh with a lock reads the committed row with SELECT ... FOR UPDATE; the copy in the entity manager may
     * be older, as another process may have moved the mark since this one read it.
     *
     * A mark that does not exist, after sample data emptied every table, starts at the start of the chain; the
     * inserted row is locked until the commit like a read one.
     *
     * @param AuditChainMark::HEAD|AuditChainMark::ANCHOR $name
     */
    private function lockedMark(string $name): AuditChainMark
    {
        $mark = $this->entityManager->find(AuditChainMark::class, $name);
        if (null === $mark) {
            $mark = AuditChainMark::startOf($name);
            $this->entityManager->persist($mark);
            $this->entityManager->flush();

            return $mark;
        }

        $this->entityManager->refresh($mark, LockMode::PESSIMISTIC_WRITE);

        return $mark;
    }

    /**
     * A missing row, after sample data emptied every table, is the start of the chain.
     */
    private function freshMark(string $name): ChainLink
    {
        $mark = $this->entityManager->find(AuditChainMark::class, $name);
        if (null === $mark) {
            return ChainLink::start();
        }

        $this->entityManager->refresh($mark);

        return $mark->link();
    }
}
