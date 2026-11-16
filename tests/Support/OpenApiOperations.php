<?php

declare(strict_types=1);

namespace App\Tests\Support;

use JsonException;

/**
 * The operations of an OpenAPI description, keyed by METHOD path, for the tests that hold the description to the API.
 */
final readonly class OpenApiOperations
{
    private const array METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /**
     * @param array<string, array{path: string, responses: array<array-key, mixed>, takesInput: bool}> $operations
     */
    private function __construct(
        public array $operations,
    ) {
    }

    /**
     * @throws JsonException
     */
    public static function fromJson(string $json): self
    {
        /** @var array{paths: array<string, array<string, array{responses?: array<array-key, mixed>, parameters?: list<array{in?: string}>, requestBody?: mixed}>>} $document */
        $document = \json_decode($json, associative: true, flags: \JSON_THROW_ON_ERROR);
        $operations = [];
        foreach ($document['paths'] as $path => $item) {
            foreach (\array_intersect_key($item, \array_flip(self::METHODS)) as $method => $operation) {
                $query = \array_filter($operation['parameters'] ?? [], static fn(array $parameter): bool => 'query' === ($parameter['in'] ?? null));
                $operations[\strtoupper($method) . ' ' . $path] = [
                    'path' => $path,
                    'responses' => $operation['responses'] ?? [],
                    'takesInput' => \array_key_exists('requestBody', $operation) || [] !== $query,
                ];
            }
        }

        return new self($operations);
    }

    /**
     * @return list<string> METHOD path, sorted
     */
    public function keys(): array
    {
        $keys = \array_keys($this->operations);
        \sort($keys);

        return $keys;
    }

    /**
     * Whether an operation takes this METHOD path, in which every {parameter} of the operation stands for one segment.
     */
    public function has(string $request): bool
    {
        foreach ($this->keys() as $operation) {
            $pattern = \preg_replace('#\\\\\{[^/]+?\\\\\}#', '[^/]+', \preg_quote($operation, '#'));
            if (1 === \preg_match('#^' . $pattern . '$#', $request)) {
                return true;
            }
        }

        return false;
    }

    public static function isProblem(mixed $response): bool
    {
        /** @var array{content?: array{'application/problem+json'?: array{schema?: array{'$ref'?: string}}}}|null $response */
        return '#/components/schemas/Problem' === ($response['content']['application/problem+json']['schema']['$ref'] ?? null);
    }
}
