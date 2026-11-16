<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class OpenApiDocumentTest extends WebTestCase
{
    #[Test]
    public function it_serves_the_api_description_as_openapi_3_1_json_without_authentication(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/docs.json');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('3.1.0', self::stringAt(self::document($client), 'openapi'));
    }

    #[Test]
    public function it_describes_the_api_routes_with_their_full_path(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/docs.json');

        self::assertArrayHasKey('/api/v1/health', self::paths(self::document($client)));
    }

    #[Test]
    public function it_leaves_its_own_route_out_of_the_description(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/docs.json');

        self::assertArrayNotHasKey('/api/v1/docs.json', self::paths(self::document($client)));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function contactDetailLists(): iterable
    {
        yield 'members' => ['GetMemberSummariesOutput', 'members', ['name', 'phone', 'address']];
        yield 'walkers of a round' => ['GetRoundOutput', 'walkers', ['name', 'phone']];
    }

    /**
     * Review focus 3 keeps a value out without consent; the field itself stays, so a client reads one shape.
     *
     * @param list<string> $nullable
     */
    #[DataProvider('contactDetailLists')]
    #[Test]
    public function it_describes_contact_details_as_always_present_and_null_when_hidden(string $schema, string $list, array $nullable): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/docs.json');

        /** @var array{components: array{schemas: array<string, array{properties: array<string, array{items?: array{required?: list<string>, properties?: array<string, array{type?: string|list<string>}>}}>}>}} $document */
        $document = self::document($client);
        $items = $document['components']['schemas'][$schema]['properties'][$list]['items'] ?? [];
        self::assertSame([], \array_diff($nullable, $items['required'] ?? []));
        foreach ($nullable as $field) {
            self::assertContains('null', (array) ($items['properties'][$field]['type'] ?? []), $field);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function document(KernelBrowser $client): array
    {
        $document = \json_decode((string) $client->getResponse()->getContent(), associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);

        return $document;
    }

    /**
     * @param array<array-key, mixed> $document
     */
    private static function stringAt(array $document, string $key): string
    {
        $value = $document[$key] ?? null;
        self::assertIsString($value);

        return $value;
    }

    /**
     * @param array<array-key, mixed> $document
     *
     * @return array<array-key, mixed>
     */
    private static function paths(array $document): array
    {
        $paths = $document['paths'] ?? null;
        self::assertIsArray($paths);

        return $paths;
    }
}
