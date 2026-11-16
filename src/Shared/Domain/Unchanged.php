<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * The default of a field that a JSON Merge Patch (RFC 7396) leaves out, which `?string` cannot tell apart from null:
 * the handler keeps the current value, where null clears it.
 */
enum Unchanged
{
    case Field;

    /**
     * The public fields of an input that carry a value or null, so validation skips the fields left out.
     *
     * @return list<string>
     */
    public static function fieldsSetIn(object $input): array
    {
        $fields = [];
        foreach (\get_object_vars($input) as $field => $value) {
            if (self::Field === $value) {
                continue;
            }

            $fields[] = (string) $field;
        }

        return $fields;
    }
}
