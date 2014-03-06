<?php

namespace Zabuto\Bundle\UserBundle\Entity;

use Doctrine\ORM\EntityRepository;

class UserRepository extends EntityRepository
{

    public function getList()
    {
        return $this->findBy(array('locked' => false), array('realname' => 'ASC'));
    }

    public function getLockedList()
    {
        return $this->findBy(array('locked' => true), array('realname' => 'ASC'));
    }

}
