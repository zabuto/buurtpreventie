<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table]
#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment extends AbstractBaseEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'comments')]
    #[Assert\NotNull]
    private ?Round $round;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $memo = '';

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

    public function getMemo(): string
    {
        return $this->memo;
    }

    public function setMemo(?string $memo): void
    {
        $this->memo = (string)$memo;
    }
}
