<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use Random\Randomizer;

/**
 * What happens to one demo round, drawn up front. Every round draws the same numbers whatever its date, so the
 * moment of loading never shifts the pattern of the rounds after it.
 */
final readonly class DemoRound
{
    public const int MAXIMUM_WALKERS = 7;

    /** Every so many rounds one is full, so a few always are. */
    private const int FULL_EVERY = 19;

    /** Every so many rounds one that was walked is cancelled after the start instead. */
    private const int CANCELLED_EVERY = 37;

    private const array COMMENTS = ['Ik neem een zaklamp mee.', 'Wie neemt de hesjes mee?', 'Ik ben er tien minuten eerder.'];
    private const array REMARKS = [
        'Poort bij de speeltuin stond open.',
        'Lantaarnpaal bij het buurthuis is kapot.',
        'Fietsen zonder slot bij de bushalte.',
    ];
    private const array INCIDENTS = [
        'Verdachte situatie bij de garageboxen, politie gebeld.',
        'Inbraaksporen aan een schuurdeur gezien, gemeld bij de politie.',
    ];

    /**
     * @param list<SampleMember> $buddies in order of signing up after the owner
     * @param 'nothing_to_report'|'remarks'|'incident' $outcome
     */
    private function __construct(
        public SampleMember $owner,
        public array $buddies,
        public ?SampleMember $leaver,
        public bool $byCoordinator,
        public int $leadDays,
        public string $meetingPoint,
        public ?string $comment,
        public bool $reported,
        public SampleMember $reporter,
        public string $outcome,
        public ?string $note,
        public bool $cancelled,
    ) {
    }

    public static function draw(Randomizer $random, int $index, Neighbourhood $neighbourhood): self
    {
        $size = self::size($random->getFloat(0, 1), $random->getInt(4, 6), $index);
        $team = self::team($random, $neighbourhood->walkers);
        $owner = $team[0] ?? $neighbourhood->coordinator;
        $byCoordinator = $random->getFloat(0, 1) < 0.1;
        $leadDays = $random->getInt(2, 14);
        $meetingPoint = $neighbourhood->meetingPoints[$random->getInt(0, \count($neighbourhood->meetingPoints) - 1)];
        $comment = $random->getFloat(0, 1) < 0.15 ? self::COMMENTS[$random->getInt(0, \count(self::COMMENTS) - 1)] : null;
        $withdrawal = $random->getFloat(0, 1) < 0.06;
        $reported = $random->getFloat(0, 1) >= 0.12;
        $reporter = $team[$random->getInt(0, $size - 1)] ?? $owner;
        [$outcome, $notes] = self::outcome($random->getFloat(0, 1));
        $note = $notes[$random->getInt(0, 1)] ?? null;

        return new self(
            $owner,
            \array_slice($team, 1, $size - 1),
            $withdrawal ? $team[$size] ?? null : null,
            $byCoordinator,
            $leadDays,
            $meetingPoint,
            $comment,
            $reported,
            $reporter,
            $outcome,
            $note,
            ($index % self::CANCELLED_EVERY) === 11,
        );
    }

    /**
     * Enough walkers in random order for a full round and one who leaves again.
     *
     * @param list<SampleMember> $walkers
     *
     * @return list<SampleMember>
     */
    private static function team(Randomizer $random, array $walkers): array
    {
        $team = [];
        for ($picked = 0; $picked <= self::MAXIMUM_WALKERS && [] !== $walkers; ++$picked) {
            $team = [...$team, ...\array_splice($walkers, $random->getInt(0, \count($walkers) - 1), 1)];
        }

        return $team;
    }

    /**
     * About a third without a buddy, most with two or three, now and then more, and every so often a full round.
     */
    private static function size(float $roll, int $many, int $index): int
    {
        if (($index % self::FULL_EVERY) === 7) {
            return self::MAXIMUM_WALKERS;
        }

        return match (true) {
            $roll < 0.32 => 1,
            $roll < 0.72 => 2,
            $roll < 0.88 => 3,
            $roll < 0.97 => $many,
            default => self::MAXIMUM_WALKERS,
        };
    }

    /**
     * Mostly nothing to report, a quarter with remarks, and now and then an incident.
     *
     * @return array{'nothing_to_report'|'remarks'|'incident', list<string>} the kind and the notes to pick from
     */
    private static function outcome(float $roll): array
    {
        return match (true) {
            $roll < 0.06 => [PatrolSteps::INCIDENT, self::INCIDENTS],
            $roll < 0.3 => [PatrolSteps::REMARKS, self::REMARKS],
            default => [PatrolSteps::NOTHING_TO_REPORT, []],
        };
    }
}
