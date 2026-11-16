<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ChangeRound;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\MeetingPointArchived;
use App\Patrol\Domain\Round\Round;
use App\Patrol\UseCase\ChangeRound\ChangeRound;
use App\Patrol\UseCase\ChangeRound\ChangeRoundHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Tests\Patrol\Domain\Round\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * The round of RoundFixture starts on 20 November 2026 at 21:00 in UTC; the clock reads 15 November.
 */
final class ChangeRoundHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';
    private const string SAME_START = '2026-11-20T22:00:00+01:00';

    private InMemoryRoundRepository $rounds;
    private InMemoryMeetingPointRepository $meetingPoints;
    private RecordingAuditTrail $auditTrail;
    private Round $round;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->meetingPoints = new InMemoryMeetingPointRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->round = RoundFixture::withWalkers(Uuid::v7(), 1);
        $this->rounds->add($this->round);
    }

    #[Test]
    public function it_moves_the_round_to_its_new_start_and_meeting_point(): void
    {
        $meetingPoint = $this->meetingPoint('Speeltuin');

        $this->handler()(new ChangeRound($this->round->id()->toRfc4122(), '2026-11-21T23:00:00+01:00', $meetingPoint->id()->toRfc4122(), 'Bij het hek'));

        $this->assertSame(
            ['2026-11-21T22:00:00+00:00', $meetingPoint, 'Bij het hek'],
            [$this->round->startsAt()->format(\DATE_ATOM), $this->round->meetingPoint(), $this->round->startNote()],
        );
    }

    #[Test]
    public function it_asks_whether_the_actor_may_change_the_round(): void
    {
        $authorization = FakeAuthorization::granting();

        $this->handler($authorization)(new ChangeRound($this->round->id()->toRfc4122(), self::SAME_START));

        $this->assertSame([['round.change', $this->round]], $authorization->asked);
    }

    #[Test]
    public function it_records_only_the_fields_that_changed(): void
    {
        $meetingPoint = $this->meetingPoint('Speeltuin');

        $this->handler()(new ChangeRound($this->round->id()->toRfc4122(), self::SAME_START, $meetingPoint->id()->toRfc4122()));

        $this->assertEquals(
            [
                new AuditedAction('round.changed', 'round', $this->round->id(), [
                    'meetingPoint' => ['old' => null, 'new' => $meetingPoint->id()->toRfc4122()],
                ]),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_keeps_the_start_meeting_point_and_note_left_out(): void
    {
        $meetingPoint = $this->meetingPoint('Speeltuin');
        $this->handler()(new ChangeRound($this->round->id()->toRfc4122(), self::SAME_START, $meetingPoint->id()->toRfc4122(), 'Bij het hek'));

        $this->handler()(new ChangeRound($this->round->id()->toRfc4122()));

        $this->assertSame(
            [RoundFixture::STARTS_AT, $meetingPoint, 'Bij het hek'],
            [$this->round->startsAt()->format(\DATE_ATOM), $this->round->meetingPoint(), $this->round->startNote()],
        );
    }

    #[Test]
    public function it_clears_the_meeting_point_and_note_given_as_null(): void
    {
        $meetingPoint = $this->meetingPoint('Speeltuin');
        $this->handler()(new ChangeRound($this->round->id()->toRfc4122(), self::SAME_START, $meetingPoint->id()->toRfc4122(), 'Bij het hek'));

        $this->handler()(new ChangeRound($this->round->id()->toRfc4122(), meetingPointId: null, startNote: null));

        $this->assertSame([null, null], [$this->round->meetingPoint(), $this->round->startNote()]);
    }

    #[Test]
    public function it_records_nothing_when_nothing_changed(): void
    {
        $this->handler()(new ChangeRound($this->round->id()->toRfc4122(), self::SAME_START));

        $this->assertSame([], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_refuses_to_move_to_an_archived_meeting_point(): void
    {
        $meetingPoint = $this->meetingPoint('Speeltuin');
        $meetingPoint->archive();

        $this->expectExceptionObject(MeetingPointArchived::forItem($meetingPoint->id()));

        $this->handler()(new ChangeRound($this->round->id()->toRfc4122(), self::SAME_START, $meetingPoint->id()->toRfc4122()));
    }

    /**
     * Archiving a meeting point keeps it on the rounds that use it, so those rounds stay changeable.
     */
    #[Test]
    public function it_keeps_the_archived_meeting_point_the_round_already_has(): void
    {
        $meetingPoint = $this->meetingPoint('Speeltuin');
        $this->handler()(new ChangeRound($this->round->id()->toRfc4122(), self::SAME_START, $meetingPoint->id()->toRfc4122()));
        $meetingPoint->archive();

        $this->handler()(new ChangeRound($this->round->id()->toRfc4122(), '2026-11-21T22:00:00+01:00', $meetingPoint->id()->toRfc4122()));

        $this->assertSame($meetingPoint, $this->round->meetingPoint());
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new ChangeRound($this->round->id()->toRfc4122(), '2026-11-21T22:00:00+01:00'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['round.change', $this->round]], $authorization->asked);
        $this->assertSame([RoundFixture::STARTS_AT, []], [$this->round->startsAt()->format(\DATE_ATOM), $this->auditTrail->recorded]);
    }

    private function meetingPoint(string $name): MeetingPoint
    {
        $meetingPoint = MeetingPoint::create($name);
        $this->meetingPoints->add($meetingPoint);

        return $meetingPoint;
    }

    private function handler(?FakeAuthorization $authorization = null): ChangeRoundHandler
    {
        return new ChangeRoundHandler(
            $this->rounds,
            $this->meetingPoints,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
        );
    }
}
