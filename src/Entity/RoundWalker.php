<?php

namespace App\Entity;

use App\Interfaces\RoundWalkerInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="App\Repository\RoundWalkerRepository")
 */
class RoundWalker extends AbstractBaseEntity implements RoundWalkerInterface
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int|null
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Round", inversedBy="walkers")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     * @Assert\NotNull()
     * @var Round|null
     */
    private $round;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotNull()
     * @var User|null
     */
    private $walker;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Round|null
     */
    public function getRound(): ?Round
    {
        return $this->round;
    }

    /**
     * @param  Round|null $round
     */
    public function setRound($round): void
    {
        $this->round = $round;
    }

    /**
     * @return User|null
     */
    public function getWalker(): ?User
    {
        return $this->walker;
    }

    /**
     * @param  User|null $walker
     */
    public function setWalker($walker): void
    {
        $this->walker = $walker;
    }
}
