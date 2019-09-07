<?php

namespace App\Repository;

use App\Entity\Round;
use App\Entity\RoundWalker;
use App\Entity\User;
use App\Interfaces\WalkerInterface;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Exception;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * RoundRepository
 */
class RoundRepository extends ServiceEntityRepository
{
    /**
     * @param  RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Round::class);
    }

    /**
     * @param  int $year
     * @param  int $month
     * @return Round[]
     * @throws Exception
     */
    public function getRoundsForMonth($year, $month)
    {
        $start = new DateTime($year . '-' . $month . '-1');
        $end = clone $start;
        $end->modify('last day of this month');

        $qb = $this->createQueryBuilder('r');
        $qb->andWhere($qb->expr()->gte('r.date', $qb->expr()->literal($start->format('Y-m-d'))));
        $qb->andWhere($qb->expr()->lte('r.date', $qb->expr()->literal($end->format('Y-m-d'))));
        $qb->addOrderBy($qb->expr()->concat($qb->expr()->concat('r.date', 'r.time'), 'r.id'), 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param  DateTime $date
     * @return Round[]
     */
    public function getRoundsForDate(DateTime $date)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->andWhere($qb->expr()->eq('r.date', $qb->expr()->literal($date->format('Y-m-d'))));
        $qb->addOrderBy($qb->expr()->concat('r.time', 'r.id'), 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param  WalkerInterface|null $walker
     * @param  string               $order
     * @return Round[]
     */
    public function getOrderedResults(?WalkerInterface $walker, string $order = 'ASC')
    {
        $qb = $this->createQueryBuilder('r');
        $qb->leftJoin(RoundWalker::class, 'rw', 'WITH', 'r.id = rw.round');
        $qb->leftJoin(User::class, 'u', 'WITH', 'rw.walker = u.id');
        $qb->addOrderBy($qb->expr()->concat($qb->expr()->concat('r.date', 'r.time'), 'r.id'), $order);
        $qb->addGroupBy('r.id');

        if (null !== $walker) {
            $qb->andWhere('u.id = :walker_id');
            $qb->setParameter('walker_id', $walker->getId());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param  WalkerInterface $walker
     * @param  string          $order
     * @return Round[]
     * @throws Exception
     */
    public function getWalkedRounds(WalkerInterface $walker, string $order = 'ASC')
    {
        $now = new DateTime();

        $qb = $this->createQueryBuilder('r');
        $qb->innerJoin(RoundWalker::class, 'rw', 'WITH', 'r.id = rw.round');
        $qb->innerJoin(User::class, 'u', 'WITH', 'rw.walker = u.id');

        $qb->andWhere('u.id = :walker_id');
        $qb->andWhere($qb->expr()->lte('r.date', ':today'));

        $qb->addOrderBy($qb->expr()->concat($qb->expr()->concat('r.date', 'r.time'), 'r.id'), $order);
        $qb->addGroupBy('r.id');

        $qb->setParameter('walker_id', $walker->getId());
        $qb->setParameter('today', $now);

        return $qb->getQuery()->getResult();
    }
}
