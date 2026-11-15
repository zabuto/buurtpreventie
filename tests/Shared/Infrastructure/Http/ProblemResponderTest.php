<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Probe\ProblemProbeController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class ProblemResponderTest extends ApiTestCase
{
    private const array DUTCH = ['HTTP_ACCEPT_LANGUAGE' => 'nl-NL,nl;q=0.9'];

    #[Test]
    public function it_answers_a_state_conflict_with_409_and_the_code_of_the_rule(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/_probe/conflict', server: self::DUTCH);

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame(
            ['type' => 'urn:buurtpreventie:problem:round_is_full', 'title' => 'Deze ronde is vol.', 'status' => 409],
            self::json($client),
        );
    }

    #[Test]
    public function it_answers_an_invalid_value_with_422(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/_probe/invalid');

        self::assertResponseStatusCodeSame(422);
        self::assertSame('urn:buurtpreventie:problem:start_in_past', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_answers_an_unknown_item_with_404(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/_probe/not-found');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('urn:buurtpreventie:problem:not_found', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_answers_a_refusal_by_the_voters_with_403(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/_probe/denied');

        self::assertResponseStatusCodeSame(403);
        self::assertSame('urn:buurtpreventie:problem:access_denied', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_lists_each_invalid_field_with_its_message(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/v1/_probe', server: [...self::DUTCH, 'CONTENT_TYPE' => 'application/json'], content: '{"name":""}');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            [
                'type' => 'urn:buurtpreventie:problem:invalid_input',
                'title' => 'Niet alle gegevens kloppen.',
                'status' => 422,
                'violations' => [['field' => 'name', 'message' => 'Deze waarde mag niet leeg zijn.']],
            ],
            self::json($client),
        );
    }

    #[Test]
    public function it_answers_unreadable_json_with_400(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/v1/_probe', server: ['CONTENT_TYPE' => 'application/json'], content: '{"name":');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('urn:buurtpreventie:problem:bad_request', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_answers_an_unknown_address_with_404(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/nothing-here');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function it_answers_a_wrong_method_with_405_and_keeps_the_allowed_methods(): void
    {
        $client = self::createClient();

        $client->request('DELETE', '/api/v1/_probe');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('Allow', 'POST');
        self::assertSame('urn:buurtpreventie:problem:method_not_allowed', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_answers_too_many_attempts_with_429_and_keeps_retry_after(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/_probe/throttled');

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('Retry-After', '60');
        self::assertSame('urn:buurtpreventie:problem:too_many_requests', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_answers_an_unexpected_failure_with_500_without_its_detail(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/_probe/unexpected', server: self::DUTCH);

        self::assertResponseStatusCodeSame(500);
        self::assertSame(
            ['type' => 'urn:buurtpreventie:problem:internal_error', 'title' => 'Er ging iets mis. Probeer het later opnieuw.', 'status' => 500],
            self::json($client),
        );
        self::assertStringNotContainsString(ProblemProbeController::UNEXPECTED_DETAIL, (string) $client->getResponse()->getContent());
    }

    #[Test]
    public function it_answers_in_english_when_the_client_asks_for_it(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/_probe/conflict', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-GB,en;q=0.8']);

        self::assertSame('This round is full.', self::json($client)['title'] ?? null);
    }

    #[Test]
    public function it_answers_in_dutch_for_a_language_it_does_not_have(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/_probe/conflict', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR']);

        self::assertSame('Deze ronde is vol.', self::json($client)['title'] ?? null);
    }

    #[Test]
    public function it_answers_in_dutch_when_the_client_names_no_language(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/_probe/conflict', server: ['HTTP_ACCEPT_LANGUAGE' => '']);

        self::assertSame('Deze ronde is vol.', self::json($client)['title'] ?? null);
    }

    #[Test]
    public function it_leaves_errors_outside_the_api_alone(): void
    {
        $client = self::createClient();

        $client->request('GET', '/nothing-here');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderNotSame('Content-Type', 'application/problem+json');
    }
}
