<?php declare(strict_types=1);

namespace App\Entity;

use App\Dto\Formatter\UserFormatter;
use App\Dto\WalkerDto;
use App\Repository\RoundWalkerRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table]
#[ORM\Entity(repositoryClass: RoundWalkerRepository::class)]
#[Map(target: WalkerDto::class)]
class RoundWalker extends AbstractBaseEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[Map(if: false)]
    private ?int $id = null;

    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'walkers')]
    #[Assert\NotNull]
    #[Map(if: false)]
    private ?Round $round = null;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Assert\NotNull]
    #[Map(target: 'name', transform: [UserFormatter::class, 'name'])]
    #[Map(target: 'email', transform: [UserFormatter::class, 'email'])]
    private ?User $walker = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Map(if: false)]
    private ?DateTime $reminded = null;

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

    public function getWalker(): ?User
    {
        return $this->walker;
    }

    public function setWalker(?User $walker): void
    {
        $this->walker = $walker;
    }

    public function getReminded(): ?DateTime
    {
        return $this->reminded;
    }

    public function setReminded(?DateTime $reminded): void
    {
        $this->reminded = $reminded;
    }

    public function wasReminded(): bool
    {
        return null !== $this->reminded;
    }
}
