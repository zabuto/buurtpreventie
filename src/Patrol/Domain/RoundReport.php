<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * The one report of a walked round: an outcome and, unless there was nothing to report, a note. A walker of the
 * round or a coordinator writes it; the author is fixed, so who may delete it never depends on who touched it last.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'round_report_round', columns: ['round_id'])]
#[ORM\Index(name: 'round_report_author', columns: ['author_id'])]
final class RoundReport
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'reports')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Round $round,
        #[ORM\Column(type: 'uuid')]
        private Uuid $authorId,
        #[ORM\ManyToOne(targetEntity: Outcome::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Outcome $outcome,
        ?string $note,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private DateTimeImmutable $reportedAt,
    ) {
        $note = \trim($note ?? '');

        $this->id = Uuid::v7();
        $this->note = '' !== $note && $this->outcome->kind()->keepsNote() ? $note : null;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function round(): Round
    {
        return $this->round;
    }

    public function authorId(): Uuid
    {
        return $this->authorId;
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
}
