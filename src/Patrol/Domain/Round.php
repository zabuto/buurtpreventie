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

    /**
     * Holds the one report of the round at most; a unique key on the round enforces it.
     *
     * @var Collection<int, RoundReport>
     */
    #[ORM\OneToMany(targetEntity: RoundReport::class, mappedBy: 'round', cascade: ['persist'], orphanRemoval: true)]
    private Collection $reports;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $buddyCallSentAt = null;

    private function __construct(
        RoundStart $start,
        #[ORM\Embedded(class: StaffingRule::class, columnPrefix: false)]
        private StaffingRule $staffingRule,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
        $this->moveTo($start);
        $this->participations = new ArrayCollection();
        $this->reports = new ArrayCollection();
    }

    /**
     * A new round with its first walker, who owns it. Who created it is in the audit log: a coordinator may create
     * it on behalf of the first walker.
     *
     * @param StaffingRule $staffingRule from the settings at this moment; it stays with the round
     * @param Uuid|null $onBehalfOf the coordinator creating the round for the first walker
     *
     * @throws StartInPast
     */
    public static function plan(
        RoundStart $start,
        Uuid $firstWalker,
        StaffingRule $staffingRule,
        DateTimeImmutable $now,
        ?Uuid $onBehalfOf = null,
    ): self {
        $start->refuseUnlessAfter($now);
        $round = new self($start, $staffingRule, $now);
        $round->participations->add(new Participation($round, $firstWalker, $now, $onBehalfOf));

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

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * The signed-up walker who joined first. Owning is not stored: when the owner withdraws, the next walker owns the
     * round.
     *
     * @return Uuid|null null only for a round without walkers, which the handler deletes
     */
    public function ownerId(): ?Uuid
    {
        return Participation::earliest($this->participations)?->memberId();
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
     * @return list<Participation> the walkers who have not been reminded of this round
     */
    public function participationsToRemind(): array
    {
        return \array_values($this->participations->filter(static fn(Participation $participation): bool => null === $participation->remindedAt())->toArray());
    }

    public function markReminded(Uuid $memberId, DateTimeImmutable $now): void
    {
        $this->participationOf($memberId)?->markReminded($now);
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
     * Records the one report of a walked round. A second report is refused rather than replacing the first, so
     * nobody overwrites the report of someone else unnoticed; deleting the report makes room for a new one.
     *
     * @throws RoundNotWalked
     * @throws RoundAlreadyReported
     */
    public function submitReport(Uuid $authorId, Outcome $outcome, ?string $note, DateTimeImmutable $now): RoundReport
    {
        $status = $this->status($now);
        if (RoundStatus::Walked !== $status) {
            throw RoundNotWalked::forRound($this->id, $status);
        }

        if (null !== $this->report()) {
            throw RoundAlreadyReported::forRound($this->id);
        }

        $report = new RoundReport($this, $authorId, $outcome, $note, $now);
        $this->reports->add($report);

        return $report;
    }

    public function report(): ?RoundReport
    {
        return $this->reports->findFirst(static fn(): bool => true);
    }

    public function removeReport(): void
    {
        $this->reports->clear();
    }

    public function buddyCallDueAt(): DateTimeImmutable
    {
        return BuddyCall::dueAt($this->createdAt, $this->startsAt);
    }

    /**
     * Whether the buddy call goes out at this moment: once per round, only when due, before the start, and while
     * the round seeks a buddy. A buddy who signs up and then withdraws causes no second call.
     */
    public function needsBuddyCall(DateTimeImmutable $now): bool
    {
        return (
            null === $this->buddyCallSentAt
                && $now >= $this->buddyCallDueAt()
                && RoundStatus::SeekingBuddy === $this->status($now)
        );
    }

    public function buddyCallSentAt(): ?DateTimeImmutable
    {
        return $this->buddyCallSentAt;
    }

    public function markBuddyCallSent(DateTimeImmutable $now): void
    {
        $this->buddyCallSentAt = $now;
    }

    /**
     * Changes when and where the round starts. The round keeps the staffing rule it was created with.
     *
     * @throws RoundHasStarted
     * @throws StartInPast
     */
    public function change(RoundStart $start, DateTimeImmutable $now): void
    {
        if ($this->hasStarted($now)) {
            throw RoundHasStarted::forRound($this->id);
        }

        $start->refuseUnlessAfter($now);
        $this->moveTo($start);
    }

    private function moveTo(RoundStart $start): void
    {
        $this->startsAt = $start->startsAt;
        $this->meetingPoint = $start->meetingPoint;
        $this->startNote = $start->note;
    }
}
