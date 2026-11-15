<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain\Round;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\MeetingPointArchived;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Where a round starts: an archived meeting point is not offered for a new start.
 */
final class RoundMeetingPointTest extends TestCase
{
    #[Test]
    public function it_refuses_to_plan_a_round_at_an_archived_meeting_point(): void
    {
        $meetingPoint = self::archived('Speeltuin');

        $this->expectException(MeetingPointArchived::class);

        Round::plan(
            start: new RoundStart(new DateTimeImmutable(RoundFixture::STARTS_AT), $meetingPoint),
            firstWalker: Uuid::v7(),
            staffingRule: new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            now: new DateTimeImmutable(RoundFixture::CREATED_AT),
        );
    }

    #[Test]
    public function it_refuses_to_move_to_an_archived_meeting_point(): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 1);

        $this->expectException(MeetingPointArchived::class);

        $round->change(new RoundStart($round->startsAt(), self::archived('Speeltuin')), new DateTimeImmutable(RoundFixture::CREATED_AT));
    }

    /**
     * Archiving takes a meeting point off the list for new starts; a round that starts there keeps it.
     */
    #[Test]
    public function it_keeps_its_own_meeting_point_once_archived(): void
    {
        $meetingPoint = MeetingPoint::create('Speeltuin');
        $round = RoundFixture::withWalkers(Uuid::v7(), 1);
        $round->change(new RoundStart($round->startsAt(), $meetingPoint), new DateTimeImmutable(RoundFixture::CREATED_AT));
        $meetingPoint->archive();

        $round->change(new RoundStart(new DateTimeImmutable('2026-11-21T21:00:00+00:00'), $meetingPoint), new DateTimeImmutable(RoundFixture::CREATED_AT));

        $this->assertSame($meetingPoint, $round->meetingPoint());
    }

    private static function archived(string $name): MeetingPoint
    {
        $meetingPoint = MeetingPoint::create($name);
        $meetingPoint->archive();

        return $meetingPoint;
    }
}
