<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ChangeMeetingPoint;

use App\Patrol\Domain\Catalogue\Coordinates;
use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * PATCH /admin/meeting-points/{id} as a JSON Merge Patch: a field left out stays, null clears it, a value replaces
 * it. Latitude and longitude go as a pair.
 */
#[Group('functional')]
final class ChangeMeetingPointEndpointTest extends ApiTestCase
{
    #[Test]
    public function it_keeps_the_fields_left_out(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $meetingPoint = self::meetingPoint();

        $client->jsonRequest('PATCH', self::path($meetingPoint), []);

        self::assertResponseStatusCodeSame(204);
        self::assertEquals(
            ['Kerk', new Coordinates(52.1, 5.1), []],
            [$meetingPoint->name(), $meetingPoint->coordinates(), self::service(RecordingAuditTrail::class)->recorded],
        );
    }

    #[Test]
    public function it_clears_the_coordinates_set_to_null(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $meetingPoint = self::meetingPoint();

        $client->jsonRequest('PATCH', self::path($meetingPoint), ['latitude' => null, 'longitude' => null]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Kerk', null], [$meetingPoint->name(), $meetingPoint->coordinates()]);
    }

    #[Test]
    public function it_refuses_null_for_the_name(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $meetingPoint = self::meetingPoint();

        $client->jsonRequest('PATCH', self::path($meetingPoint), ['name' => null]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Kerk', $meetingPoint->name());
    }

    #[Test]
    public function it_replaces_the_fields_given(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $meetingPoint = self::meetingPoint();

        $client->jsonRequest('PATCH', self::path($meetingPoint), ['latitude' => 52, 'longitude' => 5.2]);

        self::assertResponseStatusCodeSame(204);
        self::assertEquals(['Kerk', new Coordinates(52.0, 5.2)], [$meetingPoint->name(), $meetingPoint->coordinates()]);
    }

    #[Test]
    public function it_refuses_a_latitude_without_the_longitude(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $meetingPoint = self::meetingPoint();

        $client->jsonRequest('PATCH', self::path($meetingPoint), ['latitude' => 52.2]);

        self::assertResponseStatusCodeSame(422);
        self::assertEquals(new Coordinates(52.1, 5.1), $meetingPoint->coordinates());
    }

    #[Test]
    public function it_refuses_a_latitude_off_the_globe(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $meetingPoint = self::meetingPoint();

        $client->jsonRequest('PATCH', self::path($meetingPoint), ['latitude' => 91, 'longitude' => 5.2]);

        self::assertResponseStatusCodeSame(422);
        /** @var array{violations: list<array{field: string}>} $problem */
        $problem = self::json($client);
        self::assertSame(['latitude'], \array_column($problem['violations'], 'field'));
    }

    private static function meetingPoint(): MeetingPoint
    {
        $meetingPoint = MeetingPoint::create('Kerk');
        $meetingPoint->placeAt(new Coordinates(52.1, 5.1));
        self::service(InMemoryMeetingPointRepository::class)->add($meetingPoint);

        return $meetingPoint;
    }

    private static function path(MeetingPoint $meetingPoint): string
    {
        return '/api/v1/admin/meeting-points/' . $meetingPoint->id()->toRfc4122();
    }
}
