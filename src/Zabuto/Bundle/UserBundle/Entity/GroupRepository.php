<?php

namespace Zabuto\Bundle\UserBundle\Entity;

use Doctrine\ORM\EntityRepository;

class GroupRepository extends EntityRepository
{

    public function getList()
    {
        return $this->findBy(array(), array('name' => 'ASC'));
    }

}
