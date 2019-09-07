<?php

namespace App\Entity;

use App\Interfaces\RoundResultInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="App\Repository\RoundResultRepository")
 */
class RoundResult extends AbstractBaseEntity implements RoundResultInterface
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int|null
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Round", inversedBy="results")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     * @Assert\NotNull()
     * @var Round|null
     */
    private $round;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Result")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotNull()
     * @var Result|null
     */
    private $result;

    /**
     * @ORM\Column(type="text", nullable=true)
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
     * @return Result|null
     */
    public function getResult(): ?Result
    {
        return $this->result;
    }

    /**
     * @param  Result|null $result
     */
    public function setResult($result): void
    {
        $this->result = $result;
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
