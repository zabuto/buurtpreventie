<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Round;
use App\Entity\RoundWalker;
use App\Entity\User;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoundWalker>
 * @method RoundWalker|null find($id, $lockMode = null, $lockVersion = null)
 * @method RoundWalker|null findOneBy(array $criteria, array $orderBy = null)
 * @method RoundWalker[]    findAll()
 * @method RoundWalker[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoundWalkerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoundWalker::class);
    }

    public function getByRoundWalker(Round $round, User $user): ?RoundWalker
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

    /**
     * @return RoundWalker[]
     */
    public function findAllWalkersByRound(Round $round): array
    {
        $filterEnabled = $this->getEntityManager()->getFilters()->isEnabled('soft_delete');
        if ($filterEnabled) {
            $this->getEntityManager()->getFilters()->disable('soft_delete');
        }

        $qb = $this->createQueryBuilder('rw');
        $qb->innerJoin(Round::class, 'r', 'WITH', 'rw.round = r.id');
        $qb->andWhere('r.id = :round_id');
        $qb->setParameter('round_id', $round->getId());

        $result = $qb->getQuery()->getResult();

        if ($filterEnabled) {
            $this->getEntityManager()->getFilters()->enable('soft_delete');
        }

        return $result;
    }

    /**
     * @return RoundWalker[]
     */
    public function getFutureForWalker(User $user): array
    {
        $now = new DateTime();

        $qb = $this->createQueryBuilder('rw');
        $qb->innerJoin(Round::class, 'r', 'WITH', 'rw.round = r.id');
        $qb->innerJoin(User::class, 'u', 'WITH', 'rw.walker = u.id');

        $qb->andWhere('u.id = :user_id');
        $qb->andWhere($qb->expr()->gt('r.date', ':today'));

        $qb->setParameter('user_id', $user->getId());
        $qb->setParameter('today', $now);

        return $qb->getQuery()->getResult();
    }

    public function delete(RoundWalker $roundWalker): void
    {
        $this->getEntityManager()->remove($roundWalker);
        $this->getEntityManager()->flush();
    }
}
