<?php

namespace Zabuto\Bundle\UserBundle\Entity;

use Zabuto\Bundle\UserBundle\Entity\Group;
use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass="Zabuto\Bundle\UserBundle\Entity\UserRepository")
 * @ORM\Table(name="zabuto_user")
 */
class User extends BaseUser
{

    /**
     * @var integer $id
     *
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var Group[]
     *
     * @ORM\ManyToMany(targetEntity="Zabuto\Bundle\UserBundle\Entity\Group")
     * @ORM\JoinTable(name="zabuto_user_usergroup",
     *      joinColumns={@ORM\JoinColumn(name="user_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="group_id", referencedColumnName="id")}
     * )
     */
    protected $groups;

    /**
     * @var string
     *
     * @ORM\Column(name="real_name", type="string", length=150, unique=true)
     */
    protected $realname;

    /**
     * @var string
     *
     * @ORM\Column(name="address", type="string", length=150, nullable=true)
     */
    private $address;

    /**
     * @var string
     *
     * @ORM\Column(name="phone", type="string", length=30, nullable=true)
     */
    private $phone;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get naam alias
     *
     * @return string
     */
    public function getNaam()
    {
        return $this->getRealName();
    }

    /**
     * Get Gravatar email hash
     *
     * @return string
     */
    public function getGravatar()
    {
        return md5($this->getEmailCanonical());
    }

    /**
     * Set groups
     *
     * @param Group[] $groups
     * @return User
     */
    public function setGroups($groups)
    {
        $roles = array();

        if ($groups instanceof Group) {
            $collection = new ArrayCollection();
            $collection->add($groups);
            $roles = $groups->getRoles();
            $groups = $collection;
        }

        $this->setRoles($roles);
        $this->groups = $groups;
        return $this;
    }

    /**
     * Get groups
     *
     * @return Group[]
     */
    public function getGroups()
    {
        return $this->groups;
    }

    /*
     * Set email and username
     *
     * @param string $email
     * @return User
     */
    public function setEmail($email)
    {
        parent::setEmail($email);
        parent::setUsername($email);
        return $this;
    }

    /*
     * Set canonical email and username
     *
     * @param string $emailCanonical
     * @return User
     */
    public function setEmailCanonical($emailCanonical)
    {
        parent::setEmailCanonical($emailCanonical);
        parent::setUsernameCanonical($emailCanonical);
        return $this;
    }

    /**
     * Set real name
     *
     * @param string $realName
     * @return User
     */
    public function setRealName($realName)
    {
        $this->realname = $realName;
        return $this;
    }

    /**
     * Get real name
     *
     * @return string
     */
    public function getRealName()
    {
        return $this->realname;
    }

    /**
     * Set address
     *
     * @param string $address
     * @return User
     */
    public function setAddress($address)
    {
        $this->address = $address;
        return $this;
    }

    /**
     * Get address
     *
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set phone
     *
     * @param string $phone
     * @return User
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;
        return $this;
    }

    /**
     * Get phone
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

}
