<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain\Catalogue;

use App\Patrol\Domain\Catalogue\Coordinates;
use App\Patrol\Domain\Catalogue\MeetingPoint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MeetingPointTest extends TestCase
{
    #[Test]
    public function it_keys_a_renamed_meeting_point_by_its_new_name(): void
    {
        $meetingPoint = MeetingPoint::create('Café De Hoek');

        $meetingPoint->rename('Speeltuin');

        $this->assertSame(['Speeltuin', 'speeltuin'], [$meetingPoint->name(), $meetingPoint->nameKey()]);
    }

    #[Test]
    public function it_has_no_coordinates_unless_given(): void
    {
        $meetingPoint = MeetingPoint::create('Café De Hoek');

        $this->assertNull($meetingPoint->coordinates());
    }

    #[Test]
    public function it_keeps_the_coordinates_it_is_placed_at(): void
    {
        $meetingPoint = MeetingPoint::create('Café De Hoek');

        $meetingPoint->placeAt(new Coordinates(52.370_214, 4.895_168));

        $this->assertEquals(new Coordinates(52.370_214, 4.895_168), $meetingPoint->coordinates());
    }

    #[Test]
    public function it_restores_an_archived_meeting_point(): void
    {
        $meetingPoint = MeetingPoint::create('Café De Hoek');
        $meetingPoint->archive();

        $meetingPoint->restore();

        $this->assertFalse($meetingPoint->isArchived());
    }
}
