<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\MeetingPoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MeetingPoint>
 * @method MeetingPoint|null find($id, $lockMode = null, $lockVersion = null)
 * @method MeetingPoint|null findOneBy(array $criteria, array $orderBy = null)
 * @method MeetingPoint[]    findAll()
 * @method MeetingPoint[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MeetingPointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MeetingPoint::class);
    }

    public function create(MeetingPoint $meetingpoint): void
    {
        $this->getEntityManager()->persist($meetingpoint);
        $this->getEntityManager()->flush();
    }

    public function update(MeetingPoint $meetingpoint): void
    {
        $this->getEntityManager()->flush();
    }

    public function delete(MeetingPoint $meetingpoint): void
    {
        $this->getEntityManager()->remove($meetingpoint);
        $this->getEntityManager()->flush();
    }

    public function restore(MeetingPoint $meetingpoint): void
    {
        $meetingpoint->restore();
        $this->getEntityManager()->flush();
    }
}
