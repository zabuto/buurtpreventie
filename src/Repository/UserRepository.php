<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * UserRepository
 */
class UserRepository extends ServiceEntityRepository
{
    /**
     * @param  RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @param  array $roles
     * @return User[]
     */
    public function getActiveUsersForRoles(array $roles)
    {
        $qb = $this->createQueryBuilder('u');

        $orExpr = $qb->expr()->orX();
        foreach ($roles as $role) {
            $orExpr->add($qb->expr()->like('u.roles', $qb->expr()->literal(sprintf('%%%s%%', $role))));
        }

        $qb->andWhere($orExpr);
        $qb->andWhere('u.active = true');
        $qb->addOrderBy('u.name', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param  string $email
     * @return int
     */
    public function getUserCountForEmail(string $email): int
    {
        $this->getEntityManager()->getFilters()->disable('soft_delete');

        $qb = $this->createQueryBuilder('u');
        $qb->select($qb->expr()->count('u.id'));
        $qb->andWhere('u.email = :email');
        $qb->setParameter('email', $email);

        return (int)$qb->getQuery()->getSingleScalarResult();
    }
}
