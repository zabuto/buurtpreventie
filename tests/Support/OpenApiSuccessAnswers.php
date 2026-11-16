<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * The successful answers of an OpenAPI description, which a client needs to know what it gets.
 */
final readonly class OpenApiSuccessAnswers
{
    /**
     * @return list<string> METHOD path of every operation without a successful answer, or with one that does not
     *                      describe its body: none for 204, a schema for every media type otherwise
     */
    public static function undescribedIn(OpenApiOperations $operations): array
    {
        $missing = [];
        foreach ($operations->operations as $operation => $described) {
            $successes = \array_filter(
                $described['responses'],
                static fn(int|string $status): bool => (int) $status >= 200 && (int) $status < 300,
                \ARRAY_FILTER_USE_KEY,
            );
            if ([] === $successes) {
                $missing[] = $operation . ' without a success';
            }

            foreach ($successes as $status => $response) {
                if (!self::describesBody((int) $status, $response)) {
                    $missing[] = $operation . ' ' . $status;
                }
            }
        }

        return $missing;
    }

    private static function describesBody(int $status, mixed $response): bool
    {
        /** @var array{content?: array<string, array{schema?: array<string, mixed>}>} $response */
        $content = $response['content'] ?? [];
        if (204 === $status) {
            return [] === $content;
        }

        return [] !== $content && [] === \array_filter($content, static fn(array $mediaType): bool => [] === ($mediaType['schema'] ?? []));
    }
}
