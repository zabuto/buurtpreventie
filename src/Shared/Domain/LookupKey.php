<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use InvalidArgumentException;
use Normalizer;

/**
 * The search and uniqueness key of a name: whitespace collapsed, accents removed, lower case. Built in PHP rather
 * than left to a database collation, because MariaDB and MySQL disagree on which characters compare equal.
 *
 * Only combining marks go: a letter that does not decompose into base and accent, such as ø, ł, æ or ß, stays.
 */
final readonly class LookupKey
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidArgumentException for invalid UTF-8, on which Normalizer fails, or for a name without characters
     */
    public static function fromName(string $name): self
    {
        if (!\mb_check_encoding($name, 'UTF-8')) {
            throw new InvalidArgumentException('A name must be valid UTF-8.');
        }

        $collapsed = \trim((string) \preg_replace('/\s+/u', ' ', $name));
        if ('' === $collapsed) {
            throw new InvalidArgumentException('A name needs at least one character besides whitespace.');
        }

        $decomposed = Normalizer::normalize($collapsed, Normalizer::FORM_D);
        \assert(\is_string($decomposed), 'Normalizer only fails on invalid UTF-8, which is refused above.');

        return new self(\mb_strtolower((string) \preg_replace('/\p{Mn}/u', '', $decomposed)));
    }
}
