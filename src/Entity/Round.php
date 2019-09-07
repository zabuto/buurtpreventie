<?php

namespace App\Entity;

use App\Interfaces\CommentInterface;
use App\Interfaces\RoundInterface;
use App\Interfaces\RoundResultInterface;
use App\Interfaces\RoundWalkerInterface;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="App\Repository\RoundRepository")
 */
class Round extends AbstractBaseEntity implements RoundInterface
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int|null
     */
    private $id;

    /**
     * @ORM\Column(type="date")
     * @Assert\NotNull()
     * @Assert\Date()
     * @var DateTime|null
     */
    private $date;

    /**
     * @ORM\Column(type="time")
     * @Assert\NotNull()
     * @Assert\Time()
     * @var DateTime|null
     */
    private $time;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\MeetingPoint")
     * @ORM\JoinColumn(nullable=true)
     * @var MeetingPoint|null
     */
    private $meetingPoint;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\RoundWalker", mappedBy="round", cascade={"persist", "remove"})
     * @Assert\Valid()
     * @var RoundWalker[]
     */
    private $walkers;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\RoundResult", mappedBy="round")
     * @Assert\Valid()
     * @var RoundResult[]
     */
    private $results;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Comment", mappedBy="round", cascade={"persist"})
     * @Assert\Valid()
     * @var Comment[]
     */
    private $comments;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->walkers = new ArrayCollection();
        $this->results = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return DateTime|null
     */
    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    /**
     * @param  DateTime|null $date
     */
    public function setDate($date): void
    {
        $this->date = $date;
    }

    /**
     * @return DateTime|null
     */
    public function getTime(): ?DateTime
    {
        return $this->time;
    }

    /**
     * @param  DateTime|null $time
     */
    public function setTime($time): void
    {
        $this->time = $time;
    }

    /**
     * @return MeetingPoint|null
     */
    public function getMeetingPoint(): ?MeetingPoint
    {
        return $this->meetingPoint;
    }

    /**
     * @param  MeetingPoint|null $meetingPoint
     */
    public function setMeetingPoint($meetingPoint): void
    {
        $this->meetingPoint = $meetingPoint;
    }

    /**
     * @return Collection|RoundWalker[]
     */
    public function getWalkers(): Collection
    {
        return $this->walkers;
    }

    /**
     * @param  RoundWalker $walker
     */
    public function addWalker($walker): void
    {
        if (!($walker instanceof RoundWalkerInterface)) {
            throw new InvalidArgumentException('exception.round-walker.invalid');
        }

        if (!$this->walkers->contains($walker)) {
            $walker->setRound($this);
            $this->walkers[] = $walker;
        }
    }

    /**
     * @param  RoundWalker $walker
     */
    public function removeWalker($walker): void
    {
        if ($this->walkers->contains($walker)) {
            $this->walkers->removeElement($walker);
        }
    }

    /**
     * @return Collection|RoundResult[]
     */
    public function getResults(): Collection
    {
        return $this->results;
    }

    /**
     * @param  RoundResult $result
     */
    public function addResult($result): void
    {
        if (!($result instanceof RoundResultInterface)) {
            throw new InvalidArgumentException('exception.round-result.invalid');
        }

        if (!$this->results->contains($result)) {
            $result->setRound($this);
            $this->results[] = $result;
        }
    }

    /**
     * @param  RoundResult $result
     */
    public function removeResult($result): void
    {
        if ($this->results->contains($result)) {
            $this->results->removeElement($result);
        }
    }

    /**
     * @return Collection|Comment[]
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /**
     * @param  Comment $comment
     */
    public function addComment($comment): void
    {
        if (!($comment instanceof CommentInterface)) {
            throw new InvalidArgumentException('exception.round-comment.invalid');
        }

        if (!$this->comments->contains($comment)) {
            $comment->setRound($this);
            $this->comments[] = $comment;
        }
    }

    /**
     * @param  Comment $comment
     */
    public function removeComment($comment): void
    {
        if ($this->comments->contains($comment)) {
            $this->comments->removeElement($comment);
        }
    }

    /**
     * @return DateTimeImmutable|null
     * @throws Exception
     */
    public function getDatetime()
    {
        if (null === $this->date || null === $this->time) {
            return null;
        }

        $datetime = clone $this->date;
        $datetime->setTime($this->time->format('H'), $this->time->format('i'), 0, 0);

        return DateTimeImmutable::createFromMutable($datetime);
    }
}
