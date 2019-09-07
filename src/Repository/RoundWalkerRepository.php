<?php

namespace App\Repository;

use App\Entity\Round;
use App\Entity\RoundWalker;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * RoundWalkerRepository
 */
class RoundWalkerRepository extends ServiceEntityRepository
{
    /**
     * @param  RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, RoundWalker::class);
    }

    /**
     * @param  Round $round
     * @param  User  $user
     * @return RoundWalker|null
     * @throws NonUniqueResultException
     */
    public function getByRoundWalker(Round $round, User $user)
    {
        $qb = $this->createQueryBuilder('rw');
        $qb->innerJoin(Round::class, 'r', 'WITH', 'rw.round = r.id');
        $qb->innerJoin(User::class, 'u', 'WITH', 'rw.walker = u.id');

        $qb->andWhere('r.id = :round_id');
        $qb->andWhere('u.id = :user_id');

        $qb->setParameter('round_id', $round->getId());
        $qb->setParameter('user_id', $user->getId());

        return $qb->getQuery()->getOneOrNullResult();
    }
}
