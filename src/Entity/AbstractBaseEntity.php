<?php

namespace App\Entity;

use App\Traits\Blameable;
use App\Traits\SoftDeletable;
use App\Traits\Timestampable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\MappedSuperclass
 */
abstract class AbstractBaseEntity
{
    use Blameable, SoftDeletable, Timestampable;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @var UserInterface|null
     */
    protected $createdBy;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime|null
     */
    protected $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @var UserInterface|null
     */
    protected $updatedBy;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime|null
     */
    protected $updatedAt;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @var UserInterface|null
     */
    protected $deletedBy;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime|null
     */
    protected $deletedAt;
}
