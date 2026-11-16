<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListMeetingPoints;

use App\Patrol\Domain\Catalogue\Coordinates;
use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\UseCase\ListMeetingPoints\ListMeetingPoints;
use App\Patrol\UseCase\ListMeetingPoints\ListMeetingPointsHandler;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListMeetingPointsHandlerTest extends TestCase
{
    private InMemoryMeetingPointRepository $meetingPoints;

    #[Override]
    protected function setUp(): void
    {
        $this->meetingPoints = new InMemoryMeetingPointRepository();
    }

    #[Test]
    public function it_lists_the_meeting_points_to_choose_from_by_name(): void
    {
        $speeltuin = $this->stored('Speeltuin');
        $speeltuin->placeAt(new Coordinates(52.1, 5.1));
        $kerk = $this->stored('Kerk');

        $output = $this->handler()(new ListMeetingPoints());

        $this->assertSame(
            [
                ['id' => $kerk->id()->toRfc4122(), 'name' => 'Kerk', 'latitude' => null, 'longitude' => null, 'archived' => false],
                ['id' => $speeltuin->id()->toRfc4122(), 'name' => 'Speeltuin', 'latitude' => 52.1, 'longitude' => 5.1, 'archived' => false],
            ],
            $output->meetingPoints,
        );
    }

    #[Test]
    public function it_leaves_archived_meeting_points_out_of_the_choice(): void
    {
        $this->stored('Kerk')->archive();

        $output = $this->handler()(new ListMeetingPoints());

        $this->assertSame([], $output->meetingPoints);
    }

    #[Test]
    public function it_lists_archived_meeting_points_too_for_whoever_manages_them(): void
    {
        $this->stored('Kerk')->archive();
        $authorization = FakeAuthorization::granting();

        $output = $this->handler($authorization)(new ListMeetingPoints(withArchived: true));

        $this->assertSame([['Kerk', true]], \array_map(static fn(array $point): array => [$point['name'], $point['archived']], $output->meetingPoints));
        $this->assertSame([['catalogue.manage', null]], $authorization->asked);
    }

    #[Test]
    public function it_asks_any_member_for_the_choice(): void
    {
        $authorization = FakeAuthorization::granting();

        $this->handler($authorization)(new ListMeetingPoints());

        $this->assertSame([['catalogue.list', null]], $authorization->asked);
    }

    private function stored(string $name): MeetingPoint
    {
        $meetingPoint = MeetingPoint::create($name);
        $this->meetingPoints->add($meetingPoint);

        return $meetingPoint;
    }

    private function handler(?FakeAuthorization $authorization = null): ListMeetingPointsHandler
    {
        return new ListMeetingPointsHandler($this->meetingPoints, $authorization ?? FakeAuthorization::granting());
    }
}
