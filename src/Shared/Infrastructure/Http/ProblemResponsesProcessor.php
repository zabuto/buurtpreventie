<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Undefined;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Gives every operation of the description the problem answers (RFC 9457) it can give, from what the operation is:
 * a protected path can refuse who asks, a path with an id can find nothing, input can be invalid, a change can meet
 * a state that forbids it. A processor, not a describer: Nelmio adds request bodies and query parameters in its own
 * processors, after every describer. An answer documented by hand stays as it is.
 */
#[AutoconfigureTag('nelmio_api_doc.swagger.processor', ['priority' => -100])]
final readonly class ProblemResponsesProcessor
{
    /**
     * The paths security.yaml leaves open (access_control).
     */
    private const string PUBLIC_PATH = '#^/api/v1/(health|config|manifest\.webmanifest|media/brand/|auth/)#';

    private const array DESCRIPTIONS = [
        401 => 'Not logged in, or the API token is refused.',
        403 => 'The role or token scope does not allow it, or the action needs a recent login (reauthentication_required).',
        404 => 'Nothing found under this id.',
        409 => 'The current state forbids it; type names the rule.',
        422 => 'The input is invalid; violations names each field.',
    ];

    public function __invoke(Analysis $analysis): void
    {
        $paths = $analysis->openapi?->paths;
        if (null === $paths || Undefined::isDefault($paths)) {
            return;
        }

        foreach ($paths as $pathItem) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                /** @var OA\Operation|string $operation the marker of swagger-php for a method the path lacks */
                $operation = $pathItem->{$method};
                if (!$operation instanceof OA\Operation) {
                    continue;
                }

                foreach (self::statuses($pathItem->path, $method, $operation) as $status) {
                    self::describe($operation, $status);
                }
            }
        }
    }

    /**
     * @return list<int>
     */
    private static function statuses(string $path, string $method, OA\Operation $operation): array
    {
        $protected = 1 !== \preg_match(self::PUBLIC_PATH, $path);

        return \array_keys(\array_filter([
            401 => $protected,
            403 => $protected,
            404 => \str_contains($path, '{'),
            409 => $protected && 'get' !== $method,
            422 => self::takesInput($operation),
        ]));
    }

    private static function takesInput(OA\Operation $operation): bool
    {
        if (!Undefined::isDefault($operation->requestBody)) {
            return true;
        }

        if (Undefined::isDefault($operation->parameters)) {
            return false;
        }

        return \array_any($operation->parameters, static fn(OA\Parameter $parameter): bool => 'query' === $parameter->in);
    }

    private static function describe(OA\Operation $operation, int $status): void
    {
        $response = self::documented($operation, $status) ?? Util::getIndexedCollectionItem($operation, OA\Response::class, (string) $status);
        if (!Undefined::isDefault($response->description)) {
            return;
        }

        $response->description = self::DESCRIPTIONS[$status] ?? '';
        $mediaType = Util::getIndexedCollectionItem($response, OA\MediaType::class, 'application/problem+json');
        Util::getChild($mediaType, OA\Schema::class)->ref = '#/components/schemas/Problem';
    }

    /**
     * An answer an attribute documents carries its status as an integer, which the lookup of Nelmio, comparing
     * strictly with the string, would miss and then add a second time.
     */
    private static function documented(OA\Operation $operation, int $status): ?OA\Response
    {
        if (Undefined::isDefault($operation->responses)) {
            return null;
        }

        return \array_find($operation->responses, static fn(OA\Response $response): bool => (string) $response->response === (string) $status);
    }
}
