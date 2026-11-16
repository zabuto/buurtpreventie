<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Every JSON key of the API is camelCase, so a client never guesses between userId, user_id and UserId. Codes and enum
 * values are snake_case; they are values, not keys, and this test leaves them alone.
 */
#[Group('functional')]
final class OpenApiNamingTest extends WebTestCase
{
    private const string CAMEL_CASE = '/^[a-z][a-zA-Z0-9]*$/';

    #[Test]
    public function it_names_every_property_of_the_description_in_camel_case(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/v1/docs.json');
        self::assertResponseIsSuccessful();
        $document = \json_decode((string) $client->getResponse()->getContent(), associative: true, flags: \JSON_THROW_ON_ERROR);

        $names = self::propertyNames($document);

        self::assertNotEmpty($names);
        self::assertSame([], \array_values(\array_filter($names, static fn(string $name): bool => 1 !== \preg_match(self::CAMEL_CASE, $name))));
    }

    #[Test]
    public function it_names_every_query_parameter_in_camel_case(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/v1/docs.json');
        self::assertResponseIsSuccessful();
        /** @var array{paths: array<string, array<string, array{parameters?: list<array{name: string, in: string}>}>>} $document */
        $document = \json_decode((string) $client->getResponse()->getContent(), associative: true, flags: \JSON_THROW_ON_ERROR);

        $names = [];
        foreach ($document['paths'] as $operations) {
            foreach ($operations as $operation) {
                foreach ($operation['parameters'] ?? [] as $parameter) {
                    $names[] = $parameter['name'];
                }
            }
        }

        self::assertSame([], \array_values(\array_filter(\array_unique($names), static fn(string $name): bool => 1 !== \preg_match(self::CAMEL_CASE, $name))));
    }

    /**
     * The keys under every "properties" in the document, at any depth: schemas, their items and nested objects.
     *
     * @return list<string>
     */
    private static function propertyNames(mixed $node): array
    {
        if (!\is_array($node)) {
            return [];
        }

        $names = [];
        foreach ($node as $key => $child) {
            if ('properties' === $key && \is_array($child)) {
                \array_push($names, ...\array_map(strval(...), \array_keys($child)));
            }

            \array_push($names, ...self::propertyNames($child));
        }

        return \array_values(\array_unique($names));
    }
}
