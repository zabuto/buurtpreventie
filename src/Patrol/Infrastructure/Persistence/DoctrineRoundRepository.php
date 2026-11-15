<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Persistence;

use App\Patrol\Domain\MeetingPoint;
use App\Patrol\Domain\Participation;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundRepository;
use App\Shared\Domain\NotFound;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineRoundRepository implements RoundRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(Uuid $id): Round
    {
        return $this->entityManager->find(Round::class, $id) ?? throw NotFound::forId('round', $id);
    }

    /**
     * One locking query fetches the round with its walkers. The refresh hint makes it overwrite walkers the entity
     * manager loaded earlier, which a plain read may have taken from an older snapshot.
     */
    #[Override]
    public function getForUpdate(Uuid $id): Round
    {
        $round = $this->entityManager
            ->createQuery(\sprintf('SELECT r, p FROM %s r LEFT JOIN r.participations p WHERE r.id = :id', Round::class))
            ->setParameter('id', $id, 'uuid')
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult()
        ;

        return $round instanceof Round ? $round : throw NotFound::forId('round', $id);
    }

    #[Override]
    public function between(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        /** @var list<Round> */
        return $this->entityManager
            ->createQuery(\sprintf('SELECT r FROM %s r WHERE r.startsAt >= :from AND r.startsAt < :to ORDER BY r.startsAt', Round::class))
            ->setParameter('from', $from, 'utc_datetime_immutable')
            ->setParameter('to', $to, 'utc_datetime_immutable')
            ->getResult()
        ;
    }

    #[Override]
    public function upcoming(DateTimeImmutable $now): array
    {
        /** @var list<Round> */
        return $this->entityManager
            ->createQuery(\sprintf('SELECT r FROM %s r WHERE r.startsAt > :now ORDER BY r.startsAt', Round::class))
            ->setParameter('now', $now, 'utc_datetime_immutable')
            ->getResult()
        ;
    }

    #[Override]
    public function countUpcomingAt(MeetingPoint $meetingPoint, DateTimeImmutable $now): int
    {
        return (int) $this->entityManager
            ->createQuery(\sprintf('SELECT COUNT(r.id) FROM %s r WHERE r.meetingPoint = :meetingPoint AND r.startsAt > :now', Round::class))
            ->setParameter('meetingPoint', $meetingPoint->id(), 'uuid')
            ->setParameter('now', $now, 'utc_datetime_immutable')
            ->getSingleScalarResult()
        ;
    }

    #[Override]
    public function add(Round $round): void
    {
        $this->entityManager->persist($round);
    }

    #[Override]
    public function remove(Round $round): void
    {
        $this->entityManager->remove($round);
    }

    /**
     * The member is matched in a subquery, not in a join on the walkers: a filtered fetch join would load each round
     * with only this member as walker, and the count after the withdrawal would be wrong.
     */
    #[Override]
    public function upcomingWithWalker(Uuid $memberId, DateTimeImmutable $now): array
    {
        /** @var list<Round> */
        return $this->entityManager
            ->createQuery(\sprintf(
                'SELECT r FROM %s r WHERE r.startsAt > :now AND EXISTS ('
                    . 'SELECT 1 FROM %s p WHERE p.round = r AND p.memberId = :member'
                    . ') ORDER BY r.startsAt',
                Round::class,
                Participation::class,
            ))
            ->setParameter('now', $now, 'utc_datetime_immutable')
            ->setParameter('member', $memberId, 'uuid')
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult()
        ;
    }
}
