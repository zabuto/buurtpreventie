<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoundResultRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table]
#[ORM\Entity(repositoryClass: RoundResultRepository::class)]
class RoundResult extends AbstractBaseEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'results')]
    #[Assert\NotNull]
    private ?Round $round = null;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: Result::class)]
    #[Assert\NotNull]
    private ?Result $result = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $memo = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRound(): ?Round
    {
        return $this->round;
    }

    public function setRound(?Round $round): void
    {
        $this->round = $round;
    }

    public function getResult(): ?Result
    {
        return $this->result;
    }

    public function setResult(?Result $result): void
    {
        $this->result = $result;
    }

    public function getMemo(): ?string
    {
        return $this->memo;
    }

    public function setMemo(?string $memo): void
    {
        $this->memo = $memo;
    }
}
