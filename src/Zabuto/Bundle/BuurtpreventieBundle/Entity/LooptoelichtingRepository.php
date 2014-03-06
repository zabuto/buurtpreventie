<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use DateTime;
use Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema;

class LooptoelichtingRepository extends EntityRepository
{
    public function findForDate(DateTime $date)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('t');
        $qb->from('Zabuto\Bundle\BuurtpreventieBundle\Entity\Looptoelichting', 't');
        $qb->innerJoin('Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema', 's', 'WITH', 't.loopschema=s');
        $qb->where($qb->expr()->like('s.datum', ':date'));
        $qb->andWhere($qb->expr()->eq('s.actueel', '1'));
        $qb->orderBy('t.aangemaaktOp', 'ASC');
        $qb->addOrderBy('t.id', 'ASC');

        $qb->setParameter('date', $date->format('Y-m-d') . '%');

        $q = $qb->getQuery();

        return $q->getResult();
    }
}
