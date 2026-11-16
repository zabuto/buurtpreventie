<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ChangeRound;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

/**
 * PATCH /rounds/{id} as a JSON Merge Patch: a field left out stays, null clears it, a value replaces it.
 */
#[Group('functional')]
final class ChangeRoundEndpointTest extends ApiTestCase
{
    private const string STARTS_AT = '2026-11-20T21:00:00+00:00';

    #[Test]
    public function it_keeps_the_fields_left_out(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Coordinator);
        $round = self::round();

        $client->jsonRequest('PATCH', self::path($round), []);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(
            [self::STARTS_AT, 'Kerk', 'Bij het hek', []],
            [
                $round->startsAt()->format(\DATE_ATOM),
                $round->meetingPoint()?->name(),
                $round->startNote(),
                self::service(RecordingAuditTrail::class)->recorded,
            ],
        );
    }

    #[Test]
    public function it_clears_the_meeting_point_and_note_set_to_null(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Coordinator);
        $round = self::round();

        $client->jsonRequest('PATCH', self::path($round), ['meetingPointId' => null, 'startNote' => null]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([self::STARTS_AT, null, null], [$round->startsAt()->format(\DATE_ATOM), $round->meetingPoint(), $round->startNote()]);
    }

    #[Test]
    public function it_refuses_null_for_the_start(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Coordinator);
        $round = self::round();

        $client->jsonRequest('PATCH', self::path($round), ['startsAt' => null]);

        self::assertResponseStatusCodeSame(422);
        /** @var array{violations: list<array{field: string}>} $problem */
        $problem = self::json($client);
        self::assertSame(['startsAt'], \array_column($problem['violations'], 'field'));
    }

    #[Test]
    public function it_replaces_only_the_field_given(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Coordinator);
        $round = self::round();

        $client->jsonRequest('PATCH', self::path($round), ['startNote' => 'Bij de kerk']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(
            [self::STARTS_AT, 'Kerk', 'Bij de kerk'],
            [$round->startsAt()->format(\DATE_ATOM), $round->meetingPoint()?->name(), $round->startNote()],
        );
    }

    #[Test]
    public function it_refuses_a_start_that_is_no_date(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Coordinator);
        $round = self::round();

        $client->jsonRequest('PATCH', self::path($round), ['startsAt' => 'morgen']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(self::STARTS_AT, $round->startsAt()->format(\DATE_ATOM));
    }

    private static function round(): Round
    {
        $meetingPoint = MeetingPoint::create('Kerk');
        self::service(InMemoryMeetingPointRepository::class)->add($meetingPoint);
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable(self::STARTS_AT), $meetingPoint, 'Bij het hek'),
            Uuid::v7(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable('2026-11-10T09:00:00+00:00'),
        );
        self::service(InMemoryRoundRepository::class)->add($round);

        return $round;
    }

    private static function path(Round $round): string
    {
        return '/api/v1/rounds/' . $round->id()->toRfc4122();
    }
}
