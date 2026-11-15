<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use InvalidArgumentException;

/**
 * An e-mail address in the one form it is stored and compared in: trimmed and in lower case. Accents stay, because
 * an address with and one without an accent reach different mailboxes.
 */
final readonly class EmailAddress
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidArgumentException for invalid UTF-8, which lower-casing would silently corrupt, or for anything
     *                                  that is not one local part and one domain around a single @
     */
    public static function fromString(string $address): self
    {
        if (!\mb_check_encoding($address, 'UTF-8')) {
            throw new InvalidArgumentException('An e-mail address must be valid UTF-8.');
        }

        $normalised = \mb_strtolower(\trim($address));
        if (1 !== \preg_match('/^[^@\s]+@[^@\s]+$/u', $normalised)) {
            throw new InvalidArgumentException('An e-mail address needs one local part and one domain around a single @.');
        }

        return new self($normalised);
    }
}
