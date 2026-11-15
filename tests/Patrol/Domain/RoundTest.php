<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\Daypart;
use App\Patrol\Domain\MeetingPoint;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundStart;
use App\Patrol\Domain\RoundStatus;
use App\Patrol\Domain\StaffingRule;
use DateTimeImmutable;
use DateTimeZone;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Status, start and staffing of a round. Every round starts on 20 November 2026 at 22:00 in Europe/Amsterdam, 21:00 in UTC.
 */
final class RoundTest extends TestCase
{
    private Uuid $firstWalker;

    #[Override]
    protected function setUp(): void
    {
        $this->firstWalker = Uuid::v7();
    }

    #[Test]
    public function it_signs_up_the_first_walker_of_a_new_round(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1);

        $participation = $round->participationOf($this->firstWalker);

        $this->assertSame(1, $round->walkerCount());
        $this->assertNotNull($participation);
        $this->assertEquals(new DateTimeImmutable(RoundFixture::CREATED_AT), $participation->joinedAt());
        $this->assertNull($participation->onBehalfOf());
    }

    #[Test]
    public function it_records_who_created_the_round_on_behalf_of_its_first_walker(): void
    {
        $coordinator = Uuid::v7();

        $round = Round::plan(
            start: new RoundStart(new DateTimeImmutable(RoundFixture::STARTS_AT)),
            firstWalker: $this->firstWalker,
            staffingRule: new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            now: new DateTimeImmutable(RoundFixture::CREATED_AT),
            onBehalfOf: $coordinator,
        );

        $this->assertEquals($coordinator, $round->participationOf($this->firstWalker)?->onBehalfOf());
    }

    #[Test]
    public function it_is_owned_by_its_first_walker(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 3);

        $this->assertEquals($this->firstWalker, $round->ownerId());
    }

    #[Test]
    public function it_passes_the_ownership_to_the_walker_who_joined_next(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1);
        $next = Uuid::v7();
        $round->signUp($next, new DateTimeImmutable('2026-11-12T09:00:00+00:00'));
        $round->signUp(Uuid::v7(), new DateTimeImmutable('2026-11-13T09:00:00+00:00'));

        $round->withdraw($this->firstWalker, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->assertEquals($next, $round->ownerId());
    }

    /**
     * @return iterable<string, array{int, RoundStatus}>
     */
    public static function walkerCountsBeforeTheStart(): iterable
    {
        yield 'one below the minimum' => [1, RoundStatus::SeekingBuddy];
        yield 'at the minimum' => [2, RoundStatus::Staffed];
    }

    #[DataProvider('walkerCountsBeforeTheStart')]
    #[Test]
    public function it_is_staffed_once_the_minimum_is_reached(int $walkers, RoundStatus $expected): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, $walkers);

        $this->assertSame($expected, $round->status(new DateTimeImmutable(RoundFixture::BEFORE_START)));
    }

    /**
     * @return iterable<string, array{string, RoundStatus}>
     */
    public static function momentsAroundTheStart(): iterable
    {
        yield 'a minute before the start' => [RoundFixture::BEFORE_START, RoundStatus::Staffed];
        yield 'at the start' => [RoundFixture::AT_START, RoundStatus::Walked];
    }

    #[DataProvider('momentsAroundTheStart')]
    #[Test]
    public function it_counts_as_walked_only_when_past_with_the_minimum(string $now, RoundStatus $expected): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 2);

        $this->assertSame($expected, $round->status(new DateTimeImmutable($now)));
    }

    #[Test]
    public function it_counts_a_past_round_below_the_minimum_as_not_walked(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1);

        $this->assertSame(RoundStatus::NotWalked, $round->status(new DateTimeImmutable(RoundFixture::AT_START)));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function momentsInTokyo(): iterable
    {
        yield 'a minute before the start' => ['2026-11-21T05:59:00', false];
        yield 'at the start' => ['2026-11-21T06:00:00', true];
    }

    #[DataProvider('momentsInTokyo')]
    #[Test]
    public function it_starts_at_the_same_instant_in_every_time_zone(string $tokyoTime, bool $expected): void
    {
        $round = Round::plan(
            start: new RoundStart(new DateTimeImmutable('2026-11-20T22:00:00', new DateTimeZone('Europe/Amsterdam'))),
            firstWalker: $this->firstWalker,
            staffingRule: new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            now: new DateTimeImmutable(RoundFixture::CREATED_AT),
        );

        $started = $round->hasStarted(new DateTimeImmutable($tokyoTime, new DateTimeZone('Asia/Tokyo')));

        $this->assertSame($expected, $started);
    }

    #[Test]
    public function it_holds_the_start_in_utc(): void
    {
        $round = Round::plan(
            start: new RoundStart(new DateTimeImmutable('2026-11-20T22:00:00', new DateTimeZone('Europe/Amsterdam'))),
            firstWalker: $this->firstWalker,
            staffingRule: new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            now: new DateTimeImmutable(RoundFixture::CREATED_AT),
        );

        $this->assertSame(RoundFixture::STARTS_AT, $round->startsAt()->format(\DATE_ATOM));
    }

    #[Test]
    public function it_takes_its_daypart_from_the_local_start(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1);

        $this->assertSame(Daypart::Evening, $round->daypart());
    }

    #[Test]
    public function it_keeps_the_staffing_rule_it_was_created_with(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1, maximum: 3);

        $round->change(new RoundStart(new DateTimeImmutable('2026-11-21T21:00:00+00:00')));

        $this->assertEquals(new StaffingRule(minimumWalkers: 2, maximumWalkers: 3), $round->staffingRule());
    }

    #[Test]
    public function it_moves_to_a_new_start(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1);

        $round->change(new RoundStart(new DateTimeImmutable('2026-11-21T22:00:00', new DateTimeZone('Europe/Amsterdam'))));

        $this->assertSame('2026-11-21T21:00:00+00:00', $round->startsAt()->format(\DATE_ATOM));
    }

    #[Test]
    public function it_changes_its_meeting_point_and_start_note(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1);
        $meetingPoint = MeetingPoint::create('Speeltuin');

        $round->change(new RoundStart($round->startsAt(), $meetingPoint, ' Bij het hek '));

        $this->assertSame([$meetingPoint, 'Bij het hek'], [$round->meetingPoint(), $round->startNote()]);
    }

    #[Test]
    public function it_stores_no_start_note_when_it_is_blank(): void
    {
        $round = RoundFixture::withWalkers($this->firstWalker, 1);

        $round->change(new RoundStart($round->startsAt(), note: '   '));

        $this->assertNull($round->startNote());
    }
}
