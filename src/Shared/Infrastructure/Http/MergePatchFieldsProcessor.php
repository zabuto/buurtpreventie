<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Unchanged;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Undefined;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Describes a field of a JSON Merge Patch by the values a client sends. Nelmio reads Unchanged, the default of a
 * field left out, as a schema of its own and as the default value, which JSON cannot hold: the description would
 * not encode at all.
 */
#[AutoconfigureTag('nelmio_api_doc.swagger.processor', ['priority' => -100])]
final readonly class MergePatchFieldsProcessor
{
    private const string UNCHANGED = 'Unchanged';

    public function __invoke(Analysis $analysis): void
    {
        $components = $analysis->openapi?->components;
        if (null === $components || Undefined::isDefault($components) || Undefined::isDefault($components->schemas)) {
            return;
        }

        $components->schemas = \array_values(\array_filter(
            $components->schemas,
            static fn(OA\Schema $schema): bool => self::UNCHANGED !== $schema->schema,
        ));
        foreach ($components->schemas as $schema) {
            if (Undefined::isDefault($schema->properties)) {
                continue;
            }

            \array_walk($schema->properties, self::describeByWhatIsSent(...));
        }
    }

    private static function describeByWhatIsSent(OA\Property $property): void
    {
        if ($property->default instanceof Unchanged) {
            $property->default = Undefined::UNDEFINED;
        }

        if (Undefined::isDefault($property->oneOf)) {
            return;
        }

        $sent = \array_values(\array_filter(
            $property->oneOf,
            static fn(OA\Schema $schema): bool => '#/components/schemas/' . self::UNCHANGED !== $schema->ref,
        ));
        $property->oneOf = $sent;
    }
}
