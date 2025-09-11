<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByToken(string $token): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return User[]
     */
    public function findActiveMembers(): array
    {
        $roles = ['ROLE_WALK', 'ROLE_COORDINATE'];
        $qb = $this->createQueryBuilder('u');

        $orExpr = $qb->expr()->orX();
        foreach ($roles as $role) {
            $orExpr->add($qb->expr()->like('u.roles', $qb->expr()->literal(sprintf('%%%s%%', $role))));
        }

        $qb->andWhere($orExpr);
        $qb->andWhere('u.active = true');
        $qb->andWhere('u.deletedAt IS NULL');
        $qb->addOrderBy('u.name', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function getUserCountForEmail(string $email): int
    {
        $this->getEntityManager()->getFilters()->disable('soft_delete');

        $qb = $this->createQueryBuilder('u');
        $qb->select($qb->expr()->count('u.id'));
        $qb->andWhere('u.email = :email');
        $qb->setParameter('email', $email);

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    public function saveLastLogin(User $user): void
    {
        $user->setLastLogin(new DateTimeImmutable());
        $this->update($user);
    }

    public function create(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function update(User $user): void
    {
        $this->getEntityManager()->flush();
    }

    public function delete(User $user): void
    {
        $this->getEntityManager()->remove($user);
        $this->getEntityManager()->flush();
    }

    public function restore(User $user): void
    {
        $user->restore();
        $this->getEntityManager()->flush();
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if ($user instanceof User) {
            $user->setPassword($newHashedPassword);
            $this->getEntityManager()->flush();
        }
    }
}
