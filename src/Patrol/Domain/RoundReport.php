<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * What one walker reports after a walked round: an outcome and, unless there was nothing to report, a note.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'round_report_round_member', columns: ['round_id', 'member_id'])]
#[ORM\Index(name: 'round_report_member', columns: ['member_id'])]
final class RoundReport
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Outcome::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Outcome $outcome;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;

    #[ORM\Column(type: 'utc_datetime_immutable')]
    private DateTimeImmutable $reportedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'reports')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Round $round,
        #[ORM\Column(type: 'uuid')]
        private Uuid $memberId,
        Outcome $outcome,
        ?string $note,
        DateTimeImmutable $now,
    ) {
        $this->id = Uuid::v7();
        $this->revise($outcome, $note, $now);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function round(): Round
    {
        return $this->round;
    }

    public function memberId(): Uuid
    {
        return $this->memberId;
    }

    public function outcome(): Outcome
    {
        return $this->outcome;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function reportedAt(): DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function revise(Outcome $outcome, ?string $note, DateTimeImmutable $now): void
    {
        $note = \trim($note ?? '');

        $this->outcome = $outcome;
        $this->note = '' !== $note && $outcome->kind()->keepsNote() ? $note : null;
        $this->reportedAt = $now;
    }
}
