<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\CreateMeetingPoint;

use App\Patrol\Domain\Coordinates;
use App\Patrol\Domain\MeetingPoint;
use App\Patrol\Domain\MeetingPointNameTaken;
use App\Patrol\UseCase\CreateMeetingPoint\CreateMeetingPoint;
use App\Patrol\UseCase\CreateMeetingPoint\CreateMeetingPointHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\LookupKey;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CreateMeetingPointHandlerTest extends TestCase
{
    private InMemoryMeetingPointRepository $meetingPoints;
    private RecordingAuditTrail $auditTrail;

    #[Override]
    protected function setUp(): void
    {
        $this->meetingPoints = new InMemoryMeetingPointRepository();
        $this->auditTrail = new RecordingAuditTrail();
    }

    #[Test]
    public function it_creates_the_meeting_point_with_its_name_and_coordinates(): void
    {
        $output = $this->handler()(new CreateMeetingPoint(' Speeltuin ', 52.1, 5.1));

        $meetingPoint = $this->meetingPoints->get(Uuid::fromString($output->id));
        $this->assertEquals(['Speeltuin', new Coordinates(52.1, 5.1), false], [
            $meetingPoint->name(),
            $meetingPoint->coordinates(),
            $meetingPoint->isArchived(),
        ]);
    }

    #[Test]
    public function it_creates_a_meeting_point_without_coordinates(): void
    {
        $output = $this->handler()(new CreateMeetingPoint('Speeltuin', null, null));

        $this->assertNull($this->meetingPoints->get(Uuid::fromString($output->id))->coordinates());
    }

    #[Test]
    public function it_records_the_name_and_coordinates_of_the_new_meeting_point(): void
    {
        $output = $this->handler()(new CreateMeetingPoint('Speeltuin', 52.1, 5.1));

        $this->assertEquals(
            [
                new AuditedAction('meeting_point.created', 'meeting_point', Uuid::fromString($output->id), [
                    'name' => ['old' => null, 'new' => 'Speeltuin'],
                    'latitude' => ['old' => null, 'new' => 52.1],
                    'longitude' => ['old' => null, 'new' => 5.1],
                ]),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_only_the_name_of_a_new_meeting_point_without_coordinates(): void
    {
        $output = $this->handler()(new CreateMeetingPoint('Speeltuin', null, null));

        $this->assertEquals(
            [
                new AuditedAction('meeting_point.created', 'meeting_point', Uuid::fromString($output->id), [
                    'name' => ['old' => null, 'new' => 'Speeltuin'],
                ]),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_refuses_a_latitude_without_a_longitude(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->handler()(new CreateMeetingPoint('Speeltuin', 52.1, null));
    }

    #[Test]
    public function it_refuses_a_longitude_without_a_latitude(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->handler()(new CreateMeetingPoint('Speeltuin', null, 5.1));
    }

    #[Test]
    public function it_refuses_a_name_taken_by_an_active_meeting_point(): void
    {
        $existing = MeetingPoint::create('Café de Hoek');
        $this->meetingPoints->add($existing);

        $refusal = null;
        try {
            $this->handler()(new CreateMeetingPoint('cafe de hoek', null, null));
        } catch (MeetingPointNameTaken $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(MeetingPointNameTaken::class, $refusal);
        $this->assertEquals([$existing->id(), false], [$refusal->existingId, $refusal->existingIsArchived]);
    }

    /**
     * An archived name stays taken, so the admin restores the item instead of adding a look-alike.
     */
    #[Test]
    public function it_refuses_a_name_taken_by_an_archived_meeting_point(): void
    {
        $existing = MeetingPoint::create('Café de Hoek');
        $existing->archive();
        $this->meetingPoints->add($existing);

        $refusal = null;
        try {
            $this->handler()(new CreateMeetingPoint('cafe de hoek', null, null));
        } catch (MeetingPointNameTaken $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(MeetingPointNameTaken::class, $refusal);
        $this->assertEquals([$existing->id(), true], [$refusal->existingId, $refusal->existingIsArchived]);
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new CreateMeetingPoint('Speeltuin', 52.1, 5.1));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['catalogue.manage', null]], $authorization->asked);
        $this->assertSame([null, []], [
            $this->meetingPoints->findByNameKey(LookupKey::fromName('Speeltuin')),
            $this->auditTrail->recorded,
        ]);
    }

    private function handler(?FakeAuthorization $authorization = null): CreateMeetingPointHandler
    {
        return new CreateMeetingPointHandler(
            $this->meetingPoints,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
