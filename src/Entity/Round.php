<?php declare(strict_types=1);

namespace App\Entity;

use App\Dto\Formatter\DateTimeFormatter;
use App\Dto\Formatter\ToStringFormatter;
use App\Dto\RoundDto;
use App\Dto\Transformer\CollectionTransformer;
use App\Dto\Transformer\WalkerMinimumTransformer;
use App\Repository\RoundRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table]
#[ORM\Entity(repositoryClass: RoundRepository::class)]
#[Map(target: RoundDto::class)]
class Round extends AbstractBaseEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[Map(target: 'id')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Assert\NotNull]
    #[Map(if: false)]
    #[Map(target: 'date', transform: [DateTimeFormatter::class, 'date'])]
    #[Map(target: 'time', transform: [DateTimeFormatter::class, 'time'])]
    #[Map(target: 'time_of_day', transform: [DateTimeFormatter::class, 'timeOfDay'])]
    private ?DateTimeImmutable $datetime;

    #[ORM\JoinColumn(nullable: true)]
    #[ORM\ManyToOne(targetEntity: MeetingPoint::class)]
    #[Map(target: 'meetingpoint', transform: [ToStringFormatter::class, 'format'])]
    private ?MeetingPoint $meetingPoint = null;

    /** @var Collection<int, RoundWalker> */
    #[ORM\OneToMany(targetEntity: RoundWalker::class, mappedBy: 'round', cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    #[Map(target: 'walkers', transform: CollectionTransformer::class)]
    #[Map(target: 'minimum', transform: WalkerMinimumTransformer::class)]
    private Collection $walkers;

    /** @var Collection<int, RoundResult> */
    #[ORM\OneToMany(targetEntity: RoundResult::class, mappedBy: 'round', cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    #[Map(if: false)]
    private Collection $results;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'round', cascade: ['persist', 'remove'])]
    #[Assert\Valid]
    #[Map(if: false)]
    private Collection $comments;

    public function __construct()
    {
        $this->walkers = new ArrayCollection();
        $this->results = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatetime(): ?DateTimeImmutable
    {
        return $this->datetime;
    }

    public function setDatetime(?DateTimeImmutable $datetime): void
    {
        $this->datetime = $datetime;
    }

    public function getMeetingPoint(): ?MeetingPoint
    {
        return $this->meetingPoint;
    }

    public function setMeetingPoint(?MeetingPoint $meetingPoint): void
    {
        $this->meetingPoint = $meetingPoint;
    }

    /**
     * @return RoundWalker[]
     */
    public function getWalkers(): array
    {
        return $this->walkers->toArray();
    }

    public function addWalker(RoundWalker $walker): void
    {
        if (!$this->walkers->contains($walker)) {
            $walker->setRound($this);
            $this->walkers[] = $walker;
        }
    }

    public function removeWalker(RoundWalker $walker): void
    {
        if ($this->walkers->contains($walker)) {
            $this->walkers->removeElement($walker);
        }
    }

    /**
     * @return RoundResult[]
     */
    public function getResults(): array
    {
        return $this->results->toArray();
    }

    public function addResult(RoundResult $result): void
    {
        if (!$this->results->contains($result)) {
            $result->setRound($this);
            $this->results[] = $result;
        }
    }

    public function removeResult(RoundResult $result): void
    {
        if ($this->results->contains($result)) {
            $this->results->removeElement($result);
        }
    }

    /**
     * @return Comment[]
     */
    public function getComments(): array
    {
        return $this->comments->toArray();
    }

    public function addComment(Comment $comment): void
    {
        if (!$this->comments->contains($comment)) {
            $comment->setRound($this);
            $this->comments[] = $comment;
        }
    }

    public function removeComment(Comment $comment): void
    {
        if ($this->comments->contains($comment)) {
            $this->comments->removeElement($comment);
        }
    }
}
