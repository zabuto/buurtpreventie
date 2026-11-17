<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain\Statistics;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Statistics\RoundTally;
use App\Tests\Patrol\Domain\Round\RoundFixture;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RoundTallyTest extends TestCase
{
    private const string AFTER = '2026-11-21T09:00:00+00:00';

    #[Test]
    public function it_counts_each_round_by_how_it_went(): void
    {
        $walker = Uuid::v7();
        $walked = RoundFixture::withWalkers($walker, 2);
        $walked->submitReport($walker, Outcome::create('Inbraak', OutcomeKind::Incident), null, new DateTimeImmutable(self::AFTER));
        $cancelled = RoundFixture::withWalkers(Uuid::v7(), 2);
        $cancelled->cancel('Onweer', new DateTimeImmutable(self::AFTER));

        $tally = RoundTally::of(new DateTimeImmutable(self::AFTER), $walked, RoundFixture::withWalkers(Uuid::v7(), 1), $cancelled);

        $this->assertSame(
            [
                'planned' => 3,
                'walked' => 1,
                'notWalked' => 1,
                'cancelled' => 1,
                'upcoming' => 0,
                'incidents' => 1,
                'walkedPercentage' => 33.3,
                'notWalkedPercentage' => 33.3,
            ],
            $tally->toArray(),
        );
    }

    #[Test]
    public function it_counts_a_round_that_has_not_started_as_upcoming_and_leaves_it_out_of_the_percentages(): void
    {
        $walker = Uuid::v7();

        $tally = RoundTally::of(new DateTimeImmutable(RoundFixture::BEFORE_START), RoundFixture::withWalkers($walker, 1));

        $this->assertSame(
            [
                'planned' => 1,
                'walked' => 0,
                'notWalked' => 0,
                'cancelled' => 0,
                'upcoming' => 1,
                'incidents' => 0,
                'walkedPercentage' => null,
                'notWalkedPercentage' => null,
            ],
            $tally->toArray(),
        );
    }

    #[Test]
    public function it_counts_a_report_without_an_incident_as_no_incident(): void
    {
        $walker = Uuid::v7();
        $walked = RoundFixture::withWalkers($walker, 2);
        $walked->submitReport($walker, Outcome::create('Opmerkingen', OutcomeKind::Remarks), 'Lamp kapot.', new DateTimeImmutable(self::AFTER));

        $this->assertSame(0, RoundTally::of(new DateTimeImmutable(self::AFTER), $walked)->toArray()['incidents']);
    }
}
