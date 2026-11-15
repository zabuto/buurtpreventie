<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use InvalidArgumentException;

/**
 * A phone number as a walker types it, with whitespace tidied. Only its characters are checked: walkers call each
 * other from it, the app never dials it.
 */
final readonly class PhoneNumber
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidArgumentException for anything but digits, spaces, brackets and hyphens, with an optional
     *                                  leading plus, and at least six digits
     */
    public static function fromString(string $number): self
    {
        $number = \trim((string) \preg_replace('/\s+/', ' ', $number));

        if (1 !== \preg_match('/^\+?[0-9 ()\-]+$/', $number) || \preg_match_all('/[0-9]/', $number) < 6) {
            throw new InvalidArgumentException('A phone number holds digits, spaces, brackets and hyphens only.');
        }

        return new self($number);
    }
}
