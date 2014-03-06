<?php

namespace Zabuto\Bundle\UserBundle\Entity;

use FOS\UserBundle\Model\Group as BaseGroup;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Zabuto\Bundle\UserBundle\Entity\GroupRepository")
 * @ORM\Table(name="zabuto_usergroup")
 */
class Group extends BaseGroup
{

    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * Get roleList
     *
     * @return string
     */
    public function getRoleList()
    {
        $list = array();
        foreach ($this->roles as $role) {
            $list[$role] = ucfirst(strtolower(substr($role, 5)));
        }
        return $list;
    }

}