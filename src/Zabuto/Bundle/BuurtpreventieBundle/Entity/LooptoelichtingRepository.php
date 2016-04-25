<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use DateTime;
use Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema;

class LooptoelichtingRepository extends EntityRepository
{
    /**
     * Toelichtingen voor de gekozen datum
     *
     * @param DateTime $date
     * @param boolean $datetime
     * @param boolean $orderByDate
     * @return array
     */
    public function findForDate(DateTime $date, $datetime = false, $orderByDate = false)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('t');
        $qb->from('Zabuto\Bundle\BuurtpreventieBundle\Entity\Looptoelichting', 't');
        $qb->innerJoin('Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema', 's', 'WITH', 't.loopschema=s');
        $qb->where($qb->expr()->like('s.datum', ':date'));
        $qb->andWhere($qb->expr()->eq('s.actueel', '1'));
        if ($orderByDate) {
            $qb->orderBy('s.datum', 'ASC');
        } else {
            $qb->orderBy('t.aangemaaktOp', 'ASC');
        }
        $qb->addOrderBy('t.id', 'ASC');

        // Aanpassing m.b.t. looprondes. In de nieuwe situatie zijn er 
        // mogelijk meerdere looprondes per dag. We onderscheiden de
        // rondes m.b.v. datum en tijd.
        $format = 'Y-m-d';
        if ($datetime) {
            $format .= ' H:i:s';
        }
        $qb->setParameter('date', $date->format($format) . '%');

        $q = $qb->getQuery();

        return $q->getResult();
    }
}
