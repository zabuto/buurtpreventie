<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * The fields of an MCP answer that give contact details or text a member typed, found by their key so that an empty
 * value counts too.
 */
final readonly class McpFields
{
    /**
     * Objects whose `name` is the name of a place or an outcome, not of a member.
     */
    private const array NAMED_CATALOGUES = ['meetingPoint', 'meetingPoints', 'outcomes'];

    /**
     * A key with a phone number, e-mail address or address in it; `name` outside a catalogue; a `note` with text;
     * `comments` that are not empty. An answer that is no JSON object or list has no fields.
     *
     * @return list<string> the path of each such field, starting at $source
     */
    public static function forContactDetails(string $answer, string $source): array
    {
        $decoded = \json_decode($answer, associative: true);

        return \is_array($decoded) ? self::in($decoded, $source, null) : [];
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return list<string>
     */
    private static function in(array $node, string $path, ?string $parentKey): array
    {
        $found = [];
        foreach (\array_keys($node) as $key) {
            $here = $path . '.' . $key;
            if (\is_string($key) && self::gives($key, $node[$key], $parentKey)) {
                $found[] = $here;
            }

            if (\is_array($node[$key])) {
                $found = [...$found, ...self::in($node[$key], $here, \is_string($key) ? $key : $parentKey)];
            }
        }

        return $found;
    }

    private static function gives(string $key, mixed $value, ?string $parentKey): bool
    {
        return match (true) {
            1 === \preg_match('/phone|e-?mail|address/i', $key) => true,
            'name' === $key => !\in_array($parentKey, self::NAMED_CATALOGUES, strict: true),
            'note' === $key => null !== $value && '' !== $value,
            'comments' === $key => [] !== $value,
            default => false,
        };
    }
}
