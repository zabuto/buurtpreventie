<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use DateTime;

class LoopschemaRepository extends EntityRepository
{
    /**
     * Alle nog actuele aanmeldingen voor toekomstige datums
     *
     * @param User $loper
     * @return array
     */
    public function findAllActive($loper = null)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('s');
        $qb->from('Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema', 's');
        $qb->where($qb->expr()->eq('s.actueel', '1'));
        $qb->andWhere($qb->expr()->gte('s.datum', ':date'));
        $qb->orderBy('s.datum', 'ASC');

        $today = new DateTime();
        $qb->setParameter('date', $today->format('Y-m-d'));

        if (!is_null($loper)) {
            $qb->andWhere($qb->expr()->eq('s.loper', ':loper'));
            $qb->setParameter('loper', $loper);
        }

        $q = $qb->getQuery();

        return $q->getResult();
    }

    /**
     * Loopschema's voor de gekozen maand
     *
     * @param integer $year
     * @param integer $month
     * @param boolean $fromToday
     * @return array
     */
    public function findAllActiveForMonth($year, $month, $fromToday = false)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('s');
        $qb->from('Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema', 's');
        $qb->where($qb->expr()->eq('s.actueel', '1'));
        $qb->andWhere($qb->expr()->gte('s.datum', '?1'));
        $qb->andWhere($qb->expr()->lt('s.datum', '?2'));
        $qb->orderBy('s.datum', 'ASC');

        $start = new DateTime($year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01');
        $end = new DateTime($year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01');
        $end->modify('+1 month');
        if ($fromToday === true) {
            $today = new DateTime();
            $qb->setParameter(1, $today->format('Y-m-d'));
        } else {
            $qb->setParameter(1, $start->format('Y-m-d'));
        }
        $qb->setParameter(2, $end->format('Y-m-d'));

        $q = $qb->getQuery();

        return $q->getResult();
    }

    /**
     * Loopschema's voor de gekozen datum
     *
     * @param DateTime $date
     * @param User $loper
     * @param boolean $datetime
     * @return array
     */
    public function findAllActiveForDate(DateTime $date, $loper = null, $datetime = false)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('s');
        $qb->from('Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema', 's');
        $qb->where($qb->expr()->eq('s.actueel', '1'));
        $qb->andWhere($qb->expr()->like('s.datum', ':date'));
        $qb->orderBy('s.datum', 'ASC');
        
        // Aanpassing m.b.t. looprondes. In de nieuwe situatie zijn er 
        // mogelijk meerdere looprondes per dag. We onderscheiden de
        // rondes m.b.v. datum en tijd.
        $format = 'Y-m-d';
        if ($datetime) {
            $format .= ' H:i:s';
        }
        $qb->setParameter('date', $date->format($format) . '%');

        if (!is_null($loper)) {
            $qb->andWhere($qb->expr()->neq('s.loper', ':loper'));
            $qb->setParameter('loper', $loper);
        }

        $q = $qb->getQuery();

        return $q->getResult();
    }

    /**
     * Afgemelde loopschema's voor de gekozen datum
     *
     * @param DateTime $date
     * @param User $loper
     * @return array
     */
    public function findAllInactiveForDate(DateTime $date, $loper = null)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('s');
        $qb->from('Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema', 's');
        $qb->where($qb->expr()->eq('s.actueel', '0'));
        $qb->andWhere($qb->expr()->like('s.datum', ':date'));
        $qb->orderBy('s.id', 'ASC');

        $qb->setParameter('date', $date->format('Y-m-d') . '%');

        if (!is_null($loper)) {
            $qb->andWhere('s.loper = :loper');
            $qb->setParameter('loper', $loper);
        }

        $q = $qb->getQuery();

        return $q->getResult();
    }

    /**
     * Loopschema's voor gelopen ronden
     *
     * @param User $loper
     * @return array
     */
    public function findAllHistory($loper = null)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('s');
        $qb->from('Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema', 's');
        $qb->where($qb->expr()->eq('s.actueel', '1'));
        $qb->andWhere($qb->expr()->lte('s.datum', ':date'));
        $qb->orderBy('s.datum', 'DESC');

        $today = new DateTime();
        $qb->setParameter('date', $today->format('Y-m-d'));

        if (!is_null($loper)) {
            $qb->andWhere($qb->expr()->eq('s.loper', ':loper'));
            $qb->setParameter('loper', $loper);
        }

        $q = $qb->getQuery();

        return $q->getResult();
    }

    /**
     * Loopschema's voor gelopen ronden waarvan resultaat nog niet is verwerkt
     *
     * @param User $loper
     * @return array
     */
    public function findOpenResult($loper = null)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('s');
        $qb->from('Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema', 's');
        $qb->where($qb->expr()->eq('s.actueel', '1'));
        $qb->andWhere($qb->expr()->lt('s.datum', ':date'));
        $qb->andWhere($qb->expr()->isNull('s.resultaat'));
        $qb->orderBy('s.datum', 'ASC');

        $today = new DateTime();
        $qb->setParameter('date', $today->format('Y-m-d'));

        if (!is_null($loper)) {
            $qb->andWhere($qb->expr()->eq('s.loper', ':loper'));
            $qb->setParameter('loper', $loper);
        }

        $q = $qb->getQuery();

        return $q->getResult();
    }
}
