<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListMeetingPoints;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

/**
 * The meeting points and outcomes: what a member can choose, and what the admin manages under /admin/.
 */
#[Group('functional')]
final class CatalogueEndpointsTest extends ApiTestCase
{
    #[Test]
    public function it_offers_a_member_only_the_meeting_points_that_are_not_archived(): void
    {
        $client = self::client();
        self::actingAs($client);
        self::meetingPoint('Kerk');
        self::meetingPoint('Oude school')->archive();

        $client->request('GET', '/api/v1/meeting-points');

        self::assertSame(['Kerk'], self::names($client, 'meetingPoints'));
    }

    #[Test]
    public function it_offers_a_member_only_the_outcomes_that_are_not_archived(): void
    {
        $client = self::client();
        self::actingAs($client);
        self::outcome('Rustig');
        self::outcome('Oud')->archive();

        $client->request('GET', '/api/v1/outcomes');

        self::assertSame(['Rustig'], self::names($client, 'outcomes'));
    }

    #[Test]
    public function it_lists_the_archived_items_too_for_the_admin(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);
        self::meetingPoint('Kerk');
        self::meetingPoint('Oude school')->archive();

        $client->request('GET', '/api/v1/admin/meeting-points');

        self::assertSame(['Kerk', 'Oude school'], self::names($client, 'meetingPoints'));
    }

    #[Test]
    public function it_refuses_the_admin_list_to_a_walker(): void
    {
        $client = self::client();
        self::actingAs($client);

        $client->request('GET', '/api/v1/admin/outcomes');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_adds_a_meeting_point(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);

        $client->jsonRequest('POST', '/api/v1/admin/meeting-points', ['name' => 'Kerk', 'latitude' => 52.1, 'longitude' => 5.1]);

        self::assertResponseStatusCodeSame(201);
        $id = self::json($client)['id'] ?? null;
        self::assertIsString($id);
        self::assertSame('Kerk', self::service(InMemoryMeetingPointRepository::class)->get(Uuid::fromString($id))->name());
    }

    /**
     * The admin restores the item that holds the name instead of adding a look-alike, so the answer names it.
     */
    #[Test]
    public function it_names_the_archived_meeting_point_that_holds_the_name(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);
        $existing = self::meetingPoint('Oude school');
        $existing->archive();

        $client->jsonRequest('POST', '/api/v1/admin/meeting-points', ['name' => 'oude SCHOOL', 'latitude' => null, 'longitude' => null]);

        self::assertResponseStatusCodeSame(409);
        $problem = self::json($client);
        self::assertSame(
            ['urn:buurtpreventie:problem:meeting_point_name_taken', $existing->id()->toRfc4122(), true],
            [$problem['type'] ?? null, $problem['existingId'] ?? null, $problem['existingArchived'] ?? null],
        );
    }

    #[Test]
    public function it_names_the_outcome_that_holds_the_name(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);
        $existing = self::outcome('Rustig');

        $client->jsonRequest('POST', '/api/v1/admin/outcomes', ['name' => 'Rustig', 'kind' => 'remarks']);

        self::assertResponseStatusCodeSame(409);
        $problem = self::json($client);
        self::assertSame([$existing->id()->toRfc4122(), false], [$problem['existingId'] ?? null, $problem['existingArchived'] ?? null]);
    }

    #[Test]
    public function it_renames_the_outcome_in_the_path(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);
        $outcome = self::outcome('Rustig');

        $client->jsonRequest('PATCH', '/api/v1/admin/outcomes/' . $outcome->id()->toRfc4122(), ['name' => 'Niets bijzonders']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('Niets bijzonders', $outcome->name());
    }

    #[Test]
    public function it_moves_the_meeting_point_in_the_path(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);
        $meetingPoint = self::meetingPoint('Kerk');

        $client->jsonRequest('PATCH', '/api/v1/admin/meeting-points/' . $meetingPoint->id()->toRfc4122(), [
            'name' => 'Kerk',
            'latitude' => 52.2,
            'longitude' => 5.2,
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(52.2, $meetingPoint->coordinates()?->latitude);
    }

    #[Test]
    public function it_archives_and_restores_an_outcome(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);
        $outcome = self::outcome('Rustig');
        $path = \sprintf('/api/v1/admin/outcomes/%s/archive', $outcome->id()->toRfc4122());

        $client->request('POST', $path);
        self::assertResponseStatusCodeSame(204);
        self::assertTrue($outcome->isArchived());
        $client->request('DELETE', $path);

        self::assertResponseStatusCodeSame(204);
        self::assertFalse($outcome->isArchived());
    }

    #[Test]
    public function it_refuses_to_archive_a_meeting_point_where_a_round_is_still_to_start(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);
        $meetingPoint = self::meetingPoint('Kerk');
        self::service(InMemoryRoundRepository::class)->add(Round::plan(
            new RoundStart(new DateTimeImmutable('2026-11-20T21:00:00+00:00'), $meetingPoint),
            Uuid::v7(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable('2026-11-10T09:00:00+00:00'),
        ));

        $client->request('POST', \sprintf('/api/v1/admin/meeting-points/%s/archive', $meetingPoint->id()->toRfc4122()));

        self::assertResponseStatusCodeSame(409);
        self::assertSame('urn:buurtpreventie:problem:meeting_point_in_use', self::json($client)['type'] ?? null);
        self::assertFalse($meetingPoint->isArchived());
    }

    #[Test]
    public function it_refuses_a_new_outcome_to_a_coordinator(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Coordinator);

        $client->jsonRequest('POST', '/api/v1/admin/outcomes', ['name' => 'Rustig', 'kind' => 'remarks']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * One kernel for every request of the test, so the items stored before the first request stay.
     */
    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();

        return $client;
    }

    private static function meetingPoint(string $name): MeetingPoint
    {
        $meetingPoint = MeetingPoint::create($name);
        self::service(InMemoryMeetingPointRepository::class)->add($meetingPoint);

        return $meetingPoint;
    }

    private static function outcome(string $name): Outcome
    {
        $outcome = Outcome::create($name, OutcomeKind::NothingToReport);
        self::service(InMemoryOutcomeRepository::class)->add($outcome);

        return $outcome;
    }

    /**
     * @return list<string>
     */
    private static function names(KernelBrowser $client, string $list): array
    {
        self::assertResponseIsSuccessful();
        /** @var array<string, list<array{name: string}>> $answer */
        $answer = self::json($client);

        return \array_column($answer[$list] ?? [], 'name');
    }
}
