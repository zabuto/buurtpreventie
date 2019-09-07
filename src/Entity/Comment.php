<?php

namespace App\Entity;

use App\Interfaces\CommentInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="App\Repository\CommentRepository")
 */
class Comment extends AbstractBaseEntity implements CommentInterface
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int|null
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Round", inversedBy="comments")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     * @Assert\NotNull()
     * @var Round|null
     */
    private $round;

    /**
     * @ORM\Column(type="text")
     * @Assert\NotBlank()
     * @var string|null
     */
    private $memo;

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
     * @return string|null
     */
    public function getMemo(): ?string
    {
        return $this->memo;
    }

    /**
     * @param  string|null $memo
     */
    public function setMemo($memo): void
    {
        $this->memo = $memo;
    }
}
