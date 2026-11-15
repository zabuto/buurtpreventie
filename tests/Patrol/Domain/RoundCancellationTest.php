<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\Outcome;
use App\Patrol\Domain\OutcomeKind;
use App\Patrol\Domain\RoundAlreadyReported;
use App\Patrol\Domain\RoundNotStarted;
use App\Patrol\Domain\RoundNotWalked;
use App\Patrol\Domain\RoundStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * A round that went ahead on paper but was not walked, such as after a no-show or force majeure.
 */
final class RoundCancellationTest extends TestCase
{
    #[Test]
    public function it_is_cancelled_with_the_reason_once_cancelled_after_the_start(): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 2);

        $round->cancel(' Loopmaatje kwam niet ', new DateTimeImmutable(RoundFixture::AT_START));

        $this->assertSame(
            [RoundStatus::Cancelled, 'Loopmaatje kwam niet', RoundFixture::AT_START],
            [
                $round->status(new DateTimeImmutable('2026-11-21T08:00:00+00:00')),
                $round->cancellation()->reason(),
                $round
                    ->cancellation()
                    ->at()
                    ?->format(\DATE_ATOM),
            ],
        );
    }

    /**
     * Before the start, walkers withdraw or the round is deleted; cancelling records what happened during it.
     */
    #[Test]
    public function it_refuses_to_cancel_before_the_start(): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 2);

        $this->expectExceptionObject(RoundNotStarted::forRound($round->id()));

        $round->cancel(null, new DateTimeImmutable(RoundFixture::BEFORE_START));
    }

    /**
     * A report says the round was walked; it goes first, so the history never says both.
     */
    #[Test]
    public function it_refuses_to_cancel_a_reported_round(): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 2);
        $round->submitReport(Uuid::v7(), Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, new DateTimeImmutable(RoundFixture::AT_START));

        $this->expectExceptionObject(RoundAlreadyReported::forRound($round->id()));

        $round->cancel(null, new DateTimeImmutable(RoundFixture::AT_START));
    }

    #[Test]
    public function it_keeps_the_first_cancellation_when_cancelled_twice(): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 2);
        $round->cancel('Onweer', new DateTimeImmutable(RoundFixture::AT_START));

        $cancelled = $round->cancel('Loopmaatje kwam niet', new DateTimeImmutable('2026-11-20T22:00:00+00:00'));

        $this->assertSame([false, 'Onweer', RoundFixture::AT_START], [
            $cancelled,
            $round->cancellation()->reason(),
            $round
                ->cancellation()
                ->at()
                ?->format(\DATE_ATOM),
        ]);
    }

    #[Test]
    public function it_refuses_a_report_once_cancelled(): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 2);
        $round->cancel(null, new DateTimeImmutable(RoundFixture::AT_START));

        $this->expectExceptionObject(RoundNotWalked::forRound($round->id(), RoundStatus::Cancelled));

        $round->submitReport(Uuid::v7(), Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, new DateTimeImmutable(RoundFixture::AT_START));
    }

    /**
     * Cancelling records that no walk took place, whether or not the minimum had signed up.
     */
    #[Test]
    public function it_cancels_a_round_that_went_without_a_buddy(): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 1);

        $round->cancel(null, new DateTimeImmutable(RoundFixture::AT_START));

        $this->assertSame(RoundStatus::Cancelled, $round->status(new DateTimeImmutable(RoundFixture::AT_START)));
    }
}
