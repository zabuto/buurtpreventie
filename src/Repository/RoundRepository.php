<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Round;
use App\Entity\RoundWalker;
use App\Entity\User;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Round>
 * @method Round|null find($id, $lockMode = null, $lockVersion = null)
 * @method Round|null findOneBy(array $criteria, array $orderBy = null)
 * @method Round[]    findAll()
 * @method Round[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Round::class);
    }

    /**
     * @return Round[]
     */
    public function getRoundsForMonth(int $year, int $month): array
    {
        $start = new DateTime($year . '-' . $month . '-1');
        $start->setTime(0, 0, 0);

        $end = clone $start;
        $end->modify('last day of this month');
        $end->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('r');
        $qb->andWhere($qb->expr()->gte('r.datetime', $qb->expr()->literal($start->format('Y-m-d H:i:s'))));
        $qb->andWhere($qb->expr()->lte('r.datetime', $qb->expr()->literal($end->format('Y-m-d H:i:s'))));
        $qb->andWhere('r.deletedAt IS NULL');
        $qb->addOrderBy('r.datetime', 'ASC');
        $qb->addOrderBy('r.id', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Round[]
     */
    public function getRoundsForDate(DateTimeInterface $date): array
    {
        $qb = $this->createQueryBuilder('r');
        $qb->andWhere($qb->expr()->like('r.datetime', ':date_string'));
        $qb->andWhere('r.deletedAt IS NULL');
        $qb->addOrderBy('r.datetime', 'ASC');
        $qb->addOrderBy('r.id', 'ASC');

        $qb->setParameter('date_string', $date->format('Y-m-d') . '%');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Round[]
     */
    public function getOrderedResults(?User $user, string $order = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('r');
        $qb->leftJoin(RoundWalker::class, 'rw', 'WITH', 'r.id = rw.round');
        $qb->leftJoin(User::class, 'u', 'WITH', 'rw.walker = u.id');
        $qb->addOrderBy('r.datetime', $order);
        $qb->addOrderBy('r.id', $order);
        $qb->addGroupBy('r.id');

        if (null !== $user) {
            $qb->andWhere('u.id = :user_id');
            $qb->setParameter('user_id', $user->getId());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Round[]
     */
    public function getWalkedRounds(User $user, string $order = 'ASC'): array
    {
        $today = new DateTime();
        $today->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('r');
        $qb->innerJoin(RoundWalker::class, 'rw', 'WITH', 'r.id = rw.round');
        $qb->innerJoin(User::class, 'u', 'WITH', 'rw.walker = u.id');
        $qb->andWhere('u.id = :user_id');
        $qb->andWhere($qb->expr()->lte('r.datetime', ':today'));
        $qb->addOrderBy('r.datetime', $order);
        $qb->addOrderBy('r.id', $order);
        $qb->addGroupBy('r.id');

        $qb->setParameter('user_id', $user->getId());
        $qb->setParameter('today', $today);

        return $qb->getQuery()->getResult();
    }

    public function create(Round $round): void
    {
        $this->getEntityManager()->persist($round);
        $this->getEntityManager()->flush();
    }

    public function update(Round $round): void
    {
        $this->getEntityManager()->flush();
    }

    public function delete(Round $round): void
    {
        $this->getEntityManager()->remove($round);
        $this->getEntityManager()->flush();
    }
}
