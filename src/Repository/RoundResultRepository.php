<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Round;
use App\Entity\RoundResult;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoundResult>
 * @method RoundResult|null find($id, $lockMode = null, $lockVersion = null)
 * @method RoundResult|null findOneBy(array $criteria, array $orderBy = null)
 * @method RoundResult[]    findAll()
 * @method RoundResult[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoundResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoundResult::class);
    }

    /**
     * @return RoundResult[]
     */
    public function getOrderedResults(?User $user, string $order = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('rr');
        $qb->innerJoin(Round::class, 'r', 'WITH', 'rr.round = r.id');
        $qb->leftJoin(User::class, 'u', 'WITH', 'rr.createdBy = u.id');
        $qb->addOrderBy('r.datetime', $order);
        $qb->addOrderBy('u.name', $order);

        if (null !== $user) {
            $qb->andWhere('u.id = :user_id');
            $qb->setParameter('user_id', $user->getId());
        }

        return $qb->getQuery()->getResult();
    }

    public function create(RoundResult $result): void
    {
        $this->getEntityManager()->persist($result);
        $this->getEntityManager()->flush();
    }

    public function delete(RoundResult $result): void
    {
        $this->getEntityManager()->remove($result);
        $this->getEntityManager()->flush();
    }
}
