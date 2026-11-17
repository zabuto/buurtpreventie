<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListMeetingPoints;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class ListMeetingPointsToolTest extends McpTestCase
{
    #[Test]
    public function it_gives_the_same_meeting_points_as_the_rest_endpoint_without_the_archived_ones(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        $meetingPoints = self::service(InMemoryMeetingPointRepository::class);
        $meetingPoints->add(MeetingPoint::create('Speeltuin'));
        $archived = MeetingPoint::create('Oude kerk');
        $archived->archive();
        $meetingPoints->add($archived);

        $listed = self::call(self::connect($browser), 'list_meeting_points');

        self::assertSame(['Speeltuin'], self::column($listed['meetingPoints'] ?? [], 'name'));
        self::assertSame(self::rest($browser, '/api/v1/meeting-points'), $listed);
    }
}
