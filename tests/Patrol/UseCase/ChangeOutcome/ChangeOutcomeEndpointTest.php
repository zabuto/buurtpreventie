<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ChangeOutcome;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * PATCH /admin/outcomes/{id} as a JSON Merge Patch: a field left out stays, a value replaces it.
 */
#[Group('functional')]
final class ChangeOutcomeEndpointTest extends ApiTestCase
{
    #[Test]
    public function it_keeps_the_name_left_out(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $outcome = self::outcome();

        $client->jsonRequest('PATCH', self::path($outcome), []);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Rustig', []], [$outcome->name(), self::service(RecordingAuditTrail::class)->recorded]);
    }

    #[Test]
    public function it_refuses_null_for_the_name(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $outcome = self::outcome();

        $client->jsonRequest('PATCH', self::path($outcome), ['name' => null]);

        self::assertResponseStatusCodeSame(422);
        /** @var array{violations: list<array{field: string}>} $problem */
        $problem = self::json($client);
        self::assertSame(['name'], \array_column($problem['violations'], 'field'));
        self::assertSame('Rustig', $outcome->name());
    }

    #[Test]
    public function it_replaces_the_name_given(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $outcome = self::outcome();

        $client->jsonRequest('PATCH', self::path($outcome), ['name' => 'Niets bijzonders']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('Niets bijzonders', $outcome->name());
    }

    #[Test]
    public function it_refuses_an_empty_name(): void
    {
        $client = self::createClient();
        self::actingAs($client, Role::Admin);
        $outcome = self::outcome();

        $client->jsonRequest('PATCH', self::path($outcome), ['name' => ' ']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Rustig', $outcome->name());
    }

    private static function outcome(): Outcome
    {
        $outcome = Outcome::create('Rustig', OutcomeKind::NothingToReport);
        self::service(InMemoryOutcomeRepository::class)->add($outcome);

        return $outcome;
    }

    private static function path(Outcome $outcome): string
    {
        return '/api/v1/admin/outcomes/' . $outcome->id()->toRfc4122();
    }
}
