<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ArchiveMeetingPoint;

use App\Patrol\Domain\MeetingPoint;
use App\Patrol\Domain\MeetingPointInUse;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundStart;
use App\Patrol\Domain\StaffingRule;
use App\Patrol\UseCase\ArchiveMeetingPoint\ArchiveMeetingPoint;
use App\Patrol\UseCase\ArchiveMeetingPoint\ArchiveMeetingPointHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\NotFound;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class ArchiveMeetingPointHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    private InMemoryMeetingPointRepository $meetingPoints;
    private InMemoryRoundRepository $rounds;
    private RecordingAuditTrail $auditTrail;
    private MeetingPoint $speeltuin;

    #[Override]
    protected function setUp(): void
    {
        $this->meetingPoints = new InMemoryMeetingPointRepository();
        $this->rounds = new InMemoryRoundRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->speeltuin = MeetingPoint::create('Speeltuin');
        $this->meetingPoints->add($this->speeltuin);
    }

    #[Test]
    public function it_archives_the_meeting_point(): void
    {
        $this->handler()(new ArchiveMeetingPoint($this->speeltuin->id()->toRfc4122()));

        $this->assertTrue($this->speeltuin->isArchived());
    }

    #[Test]
    public function it_records_the_archiving(): void
    {
        $this->handler()(new ArchiveMeetingPoint($this->speeltuin->id()->toRfc4122()));

        $this->assertEquals(
            [new AuditedAction('meeting_point.archived', 'meeting_point', $this->speeltuin->id())],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_nothing_for_a_meeting_point_that_is_archived_already(): void
    {
        $this->speeltuin->archive();

        $this->handler()(new ArchiveMeetingPoint($this->speeltuin->id()->toRfc4122()));

        $this->assertSame([true, []], [$this->speeltuin->isArchived(), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_refuses_an_unknown_meeting_point(): void
    {
        $this->expectException(NotFound::class);

        $this->handler()(new ArchiveMeetingPoint(Uuid::v7()->toRfc4122()));
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new ArchiveMeetingPoint($this->speeltuin->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['catalogue.manage', null]], $authorization->asked);
        $this->assertSame([false, []], [$this->speeltuin->isArchived(), $this->auditTrail->recorded]);
    }

    /**
     * Walkers of an upcoming round must know where to meet; archiving would leave them a place nobody can pick.
     */
    #[Test]
    public function it_refuses_a_meeting_point_where_an_upcoming_round_starts(): void
    {
        $this->roundAt('2026-11-16T21:00:00+00:00');
        $this->roundAt('2026-11-22T21:00:00+00:00');

        $refusal = null;
        try {
            $this->handler()(new ArchiveMeetingPoint($this->speeltuin->id()->toRfc4122()));
        } catch (MeetingPointInUse $exception) {
            $refusal = $exception;
        }

        $this->assertEquals(MeetingPointInUse::byUpcomingRounds($this->speeltuin->id(), 2), $refusal);
        $this->assertSame([false, []], [$this->speeltuin->isArchived(), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_archives_a_meeting_point_where_only_rounds_that_started_began(): void
    {
        $this->roundAt('2026-11-15T12:00:00+00:00');

        $this->handler()(new ArchiveMeetingPoint($this->speeltuin->id()->toRfc4122()));

        $this->assertTrue($this->speeltuin->isArchived());
    }

    private function roundAt(string $startsAt): void
    {
        $this->rounds->add(Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt), $this->speeltuin),
            Uuid::v7(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            new DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        ));
    }

    private function handler(?FakeAuthorization $authorization = null): ArchiveMeetingPointHandler
    {
        return new ArchiveMeetingPointHandler(
            $this->meetingPoints,
            $this->rounds,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
        );
    }
}
