<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A planned round and the walkers signed up for it. Status, open spots and daypart are derived from the start, the
 * walkers and the staffing rule, so they never go stale.
 *
 * Who walked is history once the round starts: from then on nobody signs up or withdraws.
 */
#[ORM\Entity]
#[ORM\Index(name: 'round_starts_at', columns: ['starts_at'])]
#[ORM\Index(name: 'round_created_by', columns: ['created_by'])]
final class Round
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'utc_datetime_immutable')]
    private DateTimeImmutable $startsAt;

    #[ORM\ManyToOne(targetEntity: MeetingPoint::class)]
    private ?MeetingPoint $meetingPoint;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $startNote;

    /** @var Collection<int, Participation> */
    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'round', cascade: ['persist'], orphanRemoval: true)]
    private Collection $participations;

    /** @var Collection<int, RoundReport> */
    #[ORM\OneToMany(targetEntity: RoundReport::class, mappedBy: 'round', cascade: ['persist'], orphanRemoval: true)]
    private Collection $reports;

    private function __construct(
        RoundStart $start,
        #[ORM\Column(type: 'uuid')]
        private Uuid $createdBy,
        #[ORM\Embedded(class: StaffingRule::class, columnPrefix: false)]
        private StaffingRule $staffingRule,
    ) {
        $this->id = Uuid::v7();
        $this->change($start);
        $this->participations = new ArrayCollection();
        $this->reports = new ArrayCollection();
    }

    /**
     * A new round, with its creator as the first walker.
     *
     * @param StaffingRule $staffingRule from the settings at this moment; it stays with the round
     */
    public static function plan(RoundStart $start, Uuid $createdBy, StaffingRule $staffingRule, DateTimeImmutable $now): self
    {
        $round = new self($start, $createdBy, $staffingRule);
        $round->participations->add(new Participation($round, $createdBy, $now));

        return $round;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function createdBy(): Uuid
    {
        return $this->createdBy;
    }

    public function staffingRule(): StaffingRule
    {
        return $this->staffingRule;
    }

    public function meetingPoint(): ?MeetingPoint
    {
        return $this->meetingPoint;
    }

    /**
     * Free text about the start, such as where exactly to meet.
     */
    public function startNote(): ?string
    {
        return $this->startNote;
    }

    public function hasStarted(DateTimeImmutable $now): bool
    {
        return $now >= $this->startsAt;
    }

    public function status(DateTimeImmutable $now): RoundStatus
    {
        $staffed = $this->staffingRule->isStaffedBy($this->walkerCount());

        if ($this->hasStarted($now)) {
            return $staffed ? RoundStatus::Walked : RoundStatus::NotWalked;
        }

        return $staffed ? RoundStatus::Staffed : RoundStatus::SeekingBuddy;
    }

    public function openSpots(DateTimeImmutable $now): int
    {
        if ($this->hasStarted($now)) {
            return 0;
        }

        return $this->staffingRule->openSpotsFor($this->walkerCount());
    }

    public function daypart(): Daypart
    {
        return Daypart::of($this->startsAt);
    }

    public function walkerCount(): int
    {
        return $this->participations->count();
    }

    /**
     * @return list<Uuid>
     */
    public function walkerIds(): array
    {
        return \array_values($this->participations->map(static fn(Participation $participation): Uuid => $participation->memberId())->toArray());
    }

    public function isSignedUp(Uuid $memberId): bool
    {
        return null !== $this->participationOf($memberId);
    }

    public function participationOf(Uuid $memberId): ?Participation
    {
        return $this->participations->findFirst(static fn(int $key, Participation $participation): bool => $participation->memberId()->equals($memberId));
    }

    /**
     * Signs a member up. Signing up twice is no error, also in a full round: a double click on the last spot must
     * not tell the walker the round is full.
     *
     * @param Uuid|null $onBehalfOf the coordinator signing the walker up
     *
     * @throws RoundHasStarted
     * @throws RoundIsFull
     *
     * @return bool false when the member was already signed up
     */
    public function signUp(Uuid $memberId, DateTimeImmutable $now, ?Uuid $onBehalfOf = null): bool
    {
        if ($this->isSignedUp($memberId)) {
            return false;
        }

        if ($this->hasStarted($now)) {
            throw RoundHasStarted::forRound($this->id);
        }

        if ($this->staffingRule->isFullWith($this->walkerCount())) {
            throw RoundIsFull::forRound($this->id);
        }

        $this->participations->add(new Participation($this, $memberId, $now, $onBehalfOf));

        return true;
    }

    /**
     * @throws RoundHasStarted
     */
    public function withdraw(Uuid $memberId, DateTimeImmutable $now): Withdrawal
    {
        if ($this->hasStarted($now)) {
            throw RoundHasStarted::forRound($this->id);
        }

        $participation = $this->participationOf($memberId);
        if (null === $participation) {
            return Withdrawal::NotSignedUp;
        }

        $this->participations->removeElement($participation);

        return 0 === $this->walkerCount() ? Withdrawal::LastWalkerLeft : Withdrawal::Withdrawn;
    }

    /**
     * Takes a deactivated member out of the round, unless it has started: who walked stays part of its history.
     *
     * @return Withdrawal|null null when the round has started and nothing changed
     */
    public function withdrawInactiveMember(Uuid $memberId, DateTimeImmutable $now): ?Withdrawal
    {
        if ($this->hasStarted($now)) {
            return null;
        }

        return $this->withdraw($memberId, $now);
    }

    /**
     * Records what a walker reports after the round. A second report of the same walker replaces the first.
     *
     * @throws RoundNotWalked
     * @throws NotAWalkerOfThisRound
     */
    public function report(Uuid $memberId, Outcome $outcome, ?string $note, DateTimeImmutable $now): RoundReport
    {
        $status = $this->status($now);
        if (RoundStatus::Walked !== $status) {
            throw RoundNotWalked::forRound($this->id, $status);
        }

        if (!$this->isSignedUp($memberId)) {
            throw NotAWalkerOfThisRound::forMember($this->id, $memberId);
        }

        $report = $this->reportOf($memberId);
        if (null !== $report) {
            $report->revise($outcome, $note, $now);

            return $report;
        }

        $report = new RoundReport($this, $memberId, $outcome, $note, $now);
        $this->reports->add($report);

        return $report;
    }

    public function reportOf(Uuid $memberId): ?RoundReport
    {
        return $this->reports->findFirst(static fn(int $key, RoundReport $report): bool => $report->memberId()->equals($memberId));
    }

    /**
     * @return list<RoundReport>
     */
    public function reports(): array
    {
        return \array_values($this->reports->toArray());
    }

    /**
     * Changes when and where the round starts. The staffing rule stays as it was when the round was created.
     */
    public function change(RoundStart $start): void
    {
        $this->startsAt = $start->startsAt;
        $this->meetingPoint = $start->meetingPoint;
        $this->startNote = $start->note;
    }
}
