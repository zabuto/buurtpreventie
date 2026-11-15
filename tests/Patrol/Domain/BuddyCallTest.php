<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundStart;
use App\Patrol\Domain\StaffingRule;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The one message to walkers who want to hear of rounds that seek a buddy. Unless stated otherwise, the round starts
 * on 20 November 2026 at 22:00 in Europe/Amsterdam, 21:00 in UTC.
 */
final class BuddyCallTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function creationMoments(): iterable
    {
        yield 'ten days ahead: five days before' => ['2026-11-10T09:00:00+00:00', '2026-11-15T21:00:00+00:00'];
        yield 'four days ahead: three days before' => ['2026-11-16T21:00:00+00:00', '2026-11-17T21:00:00+00:00'];
        yield 'exactly five days ahead: three days before' => ['2026-11-15T21:00:00+00:00', '2026-11-17T21:00:00+00:00'];
        yield 'two days ahead: one day before' => ['2026-11-18T21:00:00+00:00', '2026-11-19T21:00:00+00:00'];
        yield 'less than a day ahead: at once' => ['2026-11-20T09:00:00+00:00', '2026-11-20T09:00:00+00:00'];
    }

    #[DataProvider('creationMoments')]
    #[Test]
    public function it_calls_for_a_buddy_on_the_first_of_five_three_or_one_day_before_the_start_after_the_creation(string $createdAt, string $expectedDue): void
    {
        $round = $this->round(RoundFixture::STARTS_AT, $createdAt);

        $this->assertSame($expectedDue, $round->buddyCallDueAt()->format(\DATE_ATOM));
    }

    /**
     * Five days before 22:00 on 27 October 2026 is 22:00 on 22 October, in summer time: 20:00 in UTC, not 21:00.
     */
    #[Test]
    public function it_keeps_the_local_time_of_day_across_a_clock_change(): void
    {
        $round = $this->round('2026-10-27T21:00:00+00:00', '2026-10-01T09:00:00+00:00');

        $this->assertSame('2026-10-22T20:00:00+00:00', $round->buddyCallDueAt()->format(\DATE_ATOM));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function momentsAroundTheDueTime(): iterable
    {
        yield 'a minute before it is due' => ['2026-11-15T20:59:00+00:00', false];
        yield 'when due' => ['2026-11-15T21:00:00+00:00', true];
        yield 'at the start' => [RoundFixture::AT_START, false];
    }

    #[DataProvider('momentsAroundTheDueTime')]
    #[Test]
    public function it_needs_a_buddy_call_from_the_due_time_until_the_start(string $now, bool $expected): void
    {
        $round = $this->round(RoundFixture::STARTS_AT, RoundFixture::CREATED_AT);

        $this->assertSame($expected, $round->needsBuddyCall(new DateTimeImmutable($now)));
    }

    #[Test]
    public function it_needs_no_buddy_call_once_staffed(): void
    {
        $round = $this->round(RoundFixture::STARTS_AT, RoundFixture::CREATED_AT);

        $round->signUp(Uuid::v7(), new DateTimeImmutable(RoundFixture::CREATED_AT));

        $this->assertFalse($round->needsBuddyCall(new DateTimeImmutable('2026-11-16T12:00:00+00:00')));
    }

    #[Test]
    public function it_calls_for_a_buddy_once(): void
    {
        $round = $this->round(RoundFixture::STARTS_AT, RoundFixture::CREATED_AT);
        $buddy = Uuid::v7();
        $round->markBuddyCallSent(new DateTimeImmutable('2026-11-15T21:00:00+00:00'));
        $round->signUp($buddy, new DateTimeImmutable('2026-11-16T09:00:00+00:00'));

        $round->withdraw($buddy, new DateTimeImmutable('2026-11-16T10:00:00+00:00'));

        $this->assertFalse($round->needsBuddyCall(new DateTimeImmutable('2026-11-17T21:00:00+00:00')));
    }

    private function round(string $startsAt, string $createdAt): Round
    {
        return Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt)),
            Uuid::v7(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            new DateTimeImmutable($createdAt),
        );
    }
}
