<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\CreateRound;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Review focus 2 through the API: a round planned at 22:00 around a clock change comes back at 22:00 with the offset
 * of that day, in the evening, wherever the API shows it.
 */
#[Group('functional')]
final class ClockChangeEndpointsTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function starts(): iterable
    {
        return ClockChangeEvenings::starts();
    }

    #[DataProvider('starts')]
    #[Test]
    public function it_shows_the_round_at_ten_in_the_evening_in_one_round(string $sent, string $expected): void
    {
        [$client, $id] = self::planned($sent);

        $client->request('GET', '/api/v1/rounds/' . $id);

        /** @var array{startsAt: string, daypart: string} $round */
        $round = self::json($client);
        self::assertSame([$expected, 'evening'], [$round['startsAt'], $round['daypart']]);
    }

    #[DataProvider('starts')]
    #[Test]
    public function it_shows_the_round_at_ten_in_the_evening_in_the_list(string $sent, string $expected): void
    {
        [$client, $id] = self::planned($sent);

        $client->request('GET', '/api/v1/rounds', ClockChangeEvenings::dayAround($expected));

        self::assertSame([[$id, $expected, 'evening']], self::listed($client));
    }

    #[DataProvider('starts')]
    #[Test]
    public function it_shows_the_round_at_ten_in_the_evening_in_the_own_rounds_still_to_walk(string $sent, string $expected): void
    {
        [$client, $id] = self::planned($sent);

        $client->request('GET', '/api/v1/me/rounds', ['view' => 'upcoming']);

        self::assertSame([[$id, $expected, 'evening']], self::listed($client));
    }

    #[DataProvider('starts')]
    #[Test]
    public function it_shows_the_round_at_ten_in_the_evening_in_every_own_round_of_its_day(string $sent, string $expected): void
    {
        [$client, $id] = self::planned($sent);

        $client->request('GET', '/api/v1/me/rounds', ['view' => 'all', ...ClockChangeEvenings::dayAround($expected)]);

        self::assertSame([[$id, $expected, 'evening']], self::listed($client));
    }

    /**
     * @return array{KernelBrowser, string}
     */
    private static function planned(string $startsAt): array
    {
        $client = self::createClient();
        $client->disableReboot();
        self::actingAs($client);
        $client->jsonRequest('POST', '/api/v1/rounds', ['startsAt' => $startsAt]);
        self::assertResponseStatusCodeSame(201);
        $id = self::json($client)['id'] ?? null;
        self::assertIsString($id);

        return [$client, $id];
    }

    /**
     * @return list<array{string, string, string}>
     */
    private static function listed(KernelBrowser $client): array
    {
        self::assertResponseIsSuccessful();
        /** @var array{rounds: list<array{id: string, startsAt: string, daypart: string}>} $answer */
        $answer = self::json($client);

        return \array_map(static fn(array $round): array => [$round['id'], $round['startsAt'], $round['daypart']], $answer['rounds']);
    }
}
