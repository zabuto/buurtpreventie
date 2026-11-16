<?php

declare(strict_types=1);

namespace App\Tests\Support;

use JsonException;

/**
 * The requests of the Postman collection that go to the API, as METHOD path with the Postman variables left in. The
 * description itself and the requests to other hosts, such as Mailpit, are no part of it.
 *
 * @phpstan-type Node array{item?: list<array<string, mixed>>, request?: array{method: string, url: array{host?: list<string>, path?: list<string>}}}
 */
final readonly class PostmanRequests
{
    /**
     * @return list<string>
     *
     * @throws JsonException
     */
    public static function fromFile(string $file): array
    {
        /** @var Node $collection */
        $collection = \json_decode((string) \file_get_contents($file), associative: true, flags: \JSON_THROW_ON_ERROR);

        return \array_values(\array_filter(
            self::collect($collection),
            static fn(string $request): bool => !\str_ends_with($request, ' /api/v1/docs.json'),
        ));
    }

    /**
     * @param Node $node
     *
     * @return list<string>
     */
    private static function collect(array $node): array
    {
        $requests = [];
        $url = $node['request']['url'] ?? [];
        if (['{{baseUrl}}'] === ($url['host'] ?? null)) {
            $requests[] = ($node['request']['method'] ?? '') . ' /' . \implode('/', $url['path'] ?? []);
        }

        foreach ($node['item'] ?? [] as $child) {
            /** @var Node $child */
            $requests = [...$requests, ...self::collect($child)];
        }

        return $requests;
    }
}
