<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\GetRound;

use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

/**
 * The rounds: listing, showing, planning, changing, deleting and cancelling them, the gaps, and the member's own.
 * The clock of the kernel stands at 16 November 2026, 11:00 in Europe/Amsterdam.
 */
#[Group('functional')]
final class RoundEndpointsTest extends ApiTestCase
{
    private const string UPCOMING = '2026-11-20T21:00:00+00:00';
    private const string PAST = '2026-11-14T21:00:00+00:00';
    private const string CREATED_AT = '2026-11-10T09:00:00+00:00';
    private const array STAFFING_FIELDS = ['status', 'walkerCount', 'minimumWalkers', 'maximumWalkers', 'openSpots', 'daypart'];

    #[Test]
    public function it_lists_the_rounds_in_a_period_in_local_time(): void
    {
        $client = self::client();
        self::actingAs($client);
        $round = self::storedRound(self::UPCOMING, Uuid::v7());

        $client->request('GET', '/api/v1/rounds', ['from' => '2026-11-16T00:00:00+01:00', 'to' => '2026-11-23T00:00:00+01:00']);

        self::assertResponseIsSuccessful();
        /** @var array{rounds: list<array{id: string, startsAt: string}>} $answer */
        $answer = self::json($client);
        self::assertSame([[$round->id()->toRfc4122(), '2026-11-20T22:00:00+01:00']], \array_map(
            static fn(array $listed): array => [$listed['id'], $listed['startsAt']],
            $answer['rounds'],
        ));
    }

    #[Test]
    public function it_refuses_a_period_of_more_than_93_days(): void
    {
        $client = self::client();
        self::actingAs($client);

        $client->request('GET', '/api/v1/rounds', ['from' => '2026-11-01T00:00:00+01:00', 'to' => '2027-02-03T00:00:00+01:00']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('urn:buurtpreventie:problem:invalid_period', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_refuses_a_list_without_a_period(): void
    {
        $client = self::client();
        self::actingAs($client);

        $client->request('GET', '/api/v1/rounds');

        self::assertResponseStatusCodeSame(422);
        self::assertSame('urn:buurtpreventie:problem:invalid_input', self::json($client)['type'] ?? null);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function roundOutputs(): iterable
    {
        yield 'the list' => ['/api/v1/rounds?from=2026-11-16T00:00:00Z&to=2026-11-23T00:00:00Z', 'rounds'];
        yield 'one round' => ['/api/v1/rounds/%s', null];
        yield 'the own rounds' => ['/api/v1/me/rounds', 'rounds'];
        yield 'the gaps' => ['/api/v1/staffing-gaps', 'gaps'];
    }

    #[DataProvider('roundOutputs')]
    #[Test]
    public function it_gives_every_round_its_status_staffing_and_daypart(string $path, ?string $listKey): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::storedRound(self::UPCOMING, $member->id());

        $client->request('GET', \sprintf($path, $round->id()->toRfc4122()));

        self::assertResponseIsSuccessful();
        $described = self::json($client);
        if (null !== $listKey) {
            $list = $described[$listKey] ?? null;
            self::assertIsArray($list);
            $described = $list[0] ?? null;
        }

        self::assertIsArray($described);
        self::assertSame(self::STAFFING_FIELDS, \array_values(\array_intersect(self::STAFFING_FIELDS, \array_keys($described))));
    }

    #[Test]
    public function it_shows_one_round_with_its_walkers(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::storedRound(self::UPCOMING, $member->id());

        $client->request('GET', '/api/v1/rounds/' . $round->id()->toRfc4122());

        self::assertResponseIsSuccessful();
        /** @var array{walkers: list<array{displayName: string}>} $answer */
        $answer = self::json($client);
        self::assertSame(['Ingelogd L.'], \array_column($answer['walkers'], 'displayName'));
    }

    #[Test]
    public function it_answers_a_round_that_does_not_exist_with_not_found(): void
    {
        $client = self::client();
        self::actingAs($client);

        $client->request('GET', '/api/v1/rounds/0193a3c0-0000-7000-8000-000000000001');

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function it_plans_a_round_with_the_member_logged_in_as_its_first_walker(): void
    {
        $client = self::client();
        $member = self::actingAs($client);

        $client->jsonRequest('POST', '/api/v1/rounds', ['startsAt' => '2026-11-20T22:00:00+01:00']);

        self::assertResponseStatusCodeSame(201);
        $id = self::json($client)['id'] ?? null;
        self::assertIsString($id);
        self::assertSame([$member->id()->toRfc4122()], \array_map(
            static fn(Uuid $walker): string => $walker->toRfc4122(),
            self::service(InMemoryRoundRepository::class)->get(Uuid::fromString($id))->walkerIds(),
        ));
    }

    #[Test]
    public function it_refuses_a_round_with_a_start_in_another_format(): void
    {
        $client = self::client();
        self::actingAs($client);

        $client->jsonRequest('POST', '/api/v1/rounds', ['startsAt' => '20-11-2026 22:00']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('urn:buurtpreventie:problem:invalid_input', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_changes_the_start_of_the_round_in_the_path(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::storedRound(self::UPCOMING, $member->id());
        $other = self::storedRound(self::UPCOMING, $member->id());

        $client->jsonRequest('PATCH', '/api/v1/rounds/' . $round->id()->toRfc4122(), [
            'roundId' => $other->id()->toRfc4122(),
            'startsAt' => '2026-11-21T20:00:00+01:00',
            'startNote' => 'Bij de kerk',
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertEquals(new DateTimeImmutable('2026-11-21T19:00:00+00:00'), $round->startsAt());
        self::assertSame('Bij de kerk', $round->startNote());
        self::assertEquals(new DateTimeImmutable(self::UPCOMING), $other->startsAt());
    }

    #[Test]
    public function it_refuses_a_change_by_a_walker_who_does_not_walk_the_round(): void
    {
        $client = self::client();
        self::actingAs($client);
        $round = self::storedRound(self::UPCOMING, Uuid::v7());

        $client->jsonRequest('PATCH', '/api/v1/rounds/' . $round->id()->toRfc4122(), ['startsAt' => '2026-11-21T20:00:00+01:00']);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_deletes_a_round_that_has_not_started(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Coordinator);
        $round = self::storedRound(self::UPCOMING, Uuid::v7());

        $client->request('DELETE', '/api/v1/rounds/' . $round->id()->toRfc4122());

        self::assertResponseStatusCodeSame(204);
        self::assertFalse(self::service(InMemoryRoundRepository::class)->has($round));
    }

    #[Test]
    public function it_cancels_a_started_round_with_a_reason(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::storedRound(self::PAST, $member->id());

        $client->jsonRequest('POST', \sprintf('/api/v1/rounds/%s/cancellation', $round->id()->toRfc4122()), ['reason' => 'Onweer']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('Onweer', $round->cancellation()->reason());
    }

    #[Test]
    public function it_cancels_a_started_round_without_a_body(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::storedRound(self::PAST, $member->id());

        $client->request('POST', \sprintf('/api/v1/rounds/%s/cancellation', $round->id()->toRfc4122()));

        self::assertResponseStatusCodeSame(204);
        self::assertSame('cancelled', $round->status(new DateTimeImmutable(self::UPCOMING))->value);
    }

    #[Test]
    public function it_lists_the_walked_rounds_of_the_member_that_wait_for_a_report(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $walked = self::storedRound(self::PAST, $member->id());
        $walked->signUp(Uuid::v7(), new DateTimeImmutable(self::CREATED_AT));
        self::storedRound(self::UPCOMING, $member->id());

        $client->request('GET', '/api/v1/me/rounds', ['view' => 'to_report']);

        self::assertResponseIsSuccessful();
        /** @var array{rounds: list<array{id: string}>} $answer */
        $answer = self::json($client);
        self::assertSame([$walked->id()->toRfc4122()], \array_column($answer['rounds'], 'id'));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidOwnRoundQueries(): iterable
    {
        yield 'every round without a period' => [['view' => 'all']];
        yield 'a view that does not exist' => [['view' => 'walked']];
        yield 'a limit above 200' => [['limit' => '201']];
        yield 'an order that does not exist' => [['view' => 'all', 'order' => 'random']];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('invalidOwnRoundQueries')]
    #[Test]
    public function it_refuses_an_invalid_query_for_the_own_rounds(array $query): void
    {
        $client = self::client();
        self::actingAs($client);

        $client->request('GET', '/api/v1/me/rounds', $query);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_limits_the_gaps_to_the_days_asked(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $soon = self::storedRound('2026-11-16T20:00:00+00:00', $member->id());
        self::storedRound('2026-11-20T20:00:00+00:00', $member->id());

        $client->request('GET', '/api/v1/staffing-gaps', ['days' => 2]);

        self::assertResponseIsSuccessful();
        $gaps = self::json($client)['gaps'];
        self::assertIsArray($gaps);
        self::assertCount(1, $gaps);
        self::assertSame($soon->id()->toRfc4122(), \is_array($gaps[0]) ? $gaps[0]['id'] : null);
    }

    #[Test]
    public function it_refuses_gaps_for_more_than_31_days(): void
    {
        $client = self::client();
        self::actingAs($client);

        $client->request('GET', '/api/v1/staffing-gaps', ['days' => 32]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_refuses_a_visitor_who_is_not_logged_in(): void
    {
        $client = self::client();

        $client->request('GET', '/api/v1/staffing-gaps');

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * One kernel for every request of the test, so the rounds and the member stored before the first request stay.
     */
    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();

        return $client;
    }

    private static function storedRound(string $startsAt, Uuid $firstWalker): Round
    {
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt)),
            $firstWalker,
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable(self::CREATED_AT),
        );
        self::service(InMemoryRoundRepository::class)->add($round);

        return $round;
    }
}
