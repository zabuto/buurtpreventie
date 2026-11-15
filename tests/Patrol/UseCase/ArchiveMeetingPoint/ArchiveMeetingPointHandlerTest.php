<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ArchiveMeetingPoint;

use App\Patrol\Domain\MeetingPoint;
use App\Patrol\UseCase\ArchiveMeetingPoint\ArchiveMeetingPoint;
use App\Patrol\UseCase\ArchiveMeetingPoint\ArchiveMeetingPointHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\NotFound;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ArchiveMeetingPointHandlerTest extends TestCase
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

    private function handler(?FakeAuthorization $authorization = null): ArchiveMeetingPointHandler
    {
        return new ArchiveMeetingPointHandler(
            $this->meetingPoints,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
