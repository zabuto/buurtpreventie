<?php

namespace App\Repository;

use App\Entity\Round;
use App\Entity\RoundResult;
use App\Entity\User;
use App\Interfaces\WalkerInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * RoundResultRepository
 */
class RoundResultRepository extends ServiceEntityRepository
{
    /**
     * @param  RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, RoundResult::class);
    }

    /**
     * @param  WalkerInterface|null $walker
     * @param  string               $order
     * @return RoundResult[]
     */
    public function getOrderedResults(?WalkerInterface $walker, string $order = 'ASC')
    {
        $qb = $this->createQueryBuilder('rr');
        $qb->innerJoin(Round::class, 'r', 'WITH', 'rr.round = r.id');
        $qb->leftJoin(User::class, 'u', 'WITH', 'rr.createdBy = u.id');
        $qb->addOrderBy($qb->expr()->concat($qb->expr()->concat('r.date', 'r.time'), 'u.name'), $order);

        if (null !== $walker) {
            $qb->andWhere('u.id = :walker_id');
            $qb->setParameter('walker_id', $walker->getId());
        }

        return $qb->getQuery()->getResult();
    }
}
