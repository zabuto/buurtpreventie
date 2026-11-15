<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ChangeMeetingPoint;

use App\Patrol\Domain\Coordinates;
use App\Patrol\Domain\MeetingPoint;
use App\Patrol\Domain\MeetingPointNameTaken;
use App\Patrol\UseCase\ChangeMeetingPoint\ChangeMeetingPoint;
use App\Patrol\UseCase\ChangeMeetingPoint\ChangeMeetingPointHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\NotFound;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ChangeMeetingPointHandlerTest extends TestCase
{
    private InMemoryMeetingPointRepository $meetingPoints;
    private RecordingAuditTrail $auditTrail;
    private MeetingPoint $speeltuin;

    #[Override]
    protected function setUp(): void
    {
        $this->meetingPoints = new InMemoryMeetingPointRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->speeltuin = MeetingPoint::create('Speeltuin');
        $this->speeltuin->placeAt(new Coordinates(52.1, 5.1));
        $this->meetingPoints->add($this->speeltuin);
    }

    #[Test]
    public function it_renames_the_meeting_point(): void
    {
        $this->handler()(new ChangeMeetingPoint($this->speeltuin->id()->toRfc4122(), 'Kerkplein', 52.1, 5.1));

        $this->assertSame('Kerkplein', $this->speeltuin->name());
    }

    #[Test]
    public function it_places_the_meeting_point_at_other_coordinates(): void
    {
        $this->handler()(new ChangeMeetingPoint($this->speeltuin->id()->toRfc4122(), 'Speeltuin', 52.2, 5.2));

        $this->assertEquals(new Coordinates(52.2, 5.2), $this->speeltuin->coordinates());
    }

    #[Test]
    public function it_clears_the_coordinates_when_both_are_left_out(): void
    {
        $this->handler()(new ChangeMeetingPoint($this->speeltuin->id()->toRfc4122(), 'Speeltuin', null, null));

        $this->assertNull($this->speeltuin->coordinates());
    }

    #[Test]
    public function it_records_only_the_fields_that_changed(): void
    {
        $this->handler()(new ChangeMeetingPoint($this->speeltuin->id()->toRfc4122(), 'Kerkplein', 52.2, 5.1));

        $this->assertEquals(
            [
                new AuditedAction('meeting_point.changed', 'meeting_point', $this->speeltuin->id(), [
                    'name' => ['old' => 'Speeltuin', 'new' => 'Kerkplein'],
                    'latitude' => ['old' => 52.1, 'new' => 52.2],
                ]),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_cleared_coordinates(): void
    {
        $this->handler()(new ChangeMeetingPoint($this->speeltuin->id()->toRfc4122(), 'Speeltuin', null, null));

        $this->assertEquals(
            [
                new AuditedAction('meeting_point.changed', 'meeting_point', $this->speeltuin->id(), [
                    'latitude' => ['old' => 52.1, 'new' => null],
                    'longitude' => ['old' => 5.1, 'new' => null],
                ]),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_nothing_when_nothing_changed(): void
    {
        $this->handler()(new ChangeMeetingPoint($this->speeltuin->id()->toRfc4122(), ' Speeltuin ', 52.1, 5.1));

        $this->assertSame([], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_allows_another_spelling_of_its_own_name(): void
    {
        $this->handler()(new ChangeMeetingPoint($this->speeltuin->id()->toRfc4122(), 'speeltuin', 52.1, 5.1));

        $this->assertSame('speeltuin', $this->speeltuin->name());
    }

    #[Test]
    public function it_refuses_a_name_taken_by_an_active_meeting_point(): void
    {
        $existing = MeetingPoint::create('Café de Hoek');
        $this->meetingPoints->add($existing);

        $refusal = null;
        try {
            $this->handler()(new ChangeMeetingPoint($this->speeltuin->id()->toRfc4122(), 'cafe de hoek', 52.1, 5.1));
        } catch (MeetingPointNameTaken $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(MeetingPointNameTaken::class, $refusal);
        $this->assertEquals([$existing->id(), false], [$refusal->existingId, $refusal->existingIsArchived]);
    }

    #[Test]
    public function it_refuses_a_name_taken_by_an_archived_meeting_point(): void
    {
        $existing = MeetingPoint::create('Café de Hoek');
        $existing->archive();
        $this->meetingPoints->add($existing);

        $refusal = null;
        try {
            $this->handler()(new ChangeMeetingPoint($this->speeltuin->id()->toRfc4122(), 'cafe de hoek', 52.1, 5.1));
        } catch (MeetingPointNameTaken $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(MeetingPointNameTaken::class, $refusal);
        $this->assertEquals([$existing->id(), true], [$refusal->existingId, $refusal->existingIsArchived]);
    }

    #[Test]
    public function it_refuses_a_latitude_without_a_longitude(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->handler()(new ChangeMeetingPoint($this->speeltuin->id()->toRfc4122(), 'Speeltuin', 52.2, null));
    }

    #[Test]
    public function it_refuses_a_longitude_without_a_latitude(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->handler()(new ChangeMeetingPoint($this->speeltuin->id()->toRfc4122(), 'Speeltuin', null, 5.2));
    }

    #[Test]
    public function it_refuses_an_unknown_meeting_point(): void
    {
        $this->expectException(NotFound::class);

        $this->handler()(new ChangeMeetingPoint(Uuid::v7()->toRfc4122(), 'Kerkplein', null, null));
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new ChangeMeetingPoint($this->speeltuin->id()->toRfc4122(), 'Kerkplein', 52.2, 5.2));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['catalogue.manage', null]], $authorization->asked);
        $this->assertEquals(['Speeltuin', new Coordinates(52.1, 5.1), []], [
            $this->speeltuin->name(),
            $this->speeltuin->coordinates(),
            $this->auditTrail->recorded,
        ]);
    }

    private function handler(?FakeAuthorization $authorization = null): ChangeMeetingPointHandler
    {
        return new ChangeMeetingPointHandler(
            $this->meetingPoints,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
