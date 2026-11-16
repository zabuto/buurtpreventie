<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Normalizer;

/**
 * The search and uniqueness key of a name: no case, accents or punctuation, and single spaces. Built in PHP rather
 * than left to a database collation, because MariaDB and MySQL disagree on which characters compare equal.
 *
 * Only combining marks go: a letter that does not decompose into base and accent, such as ø, ł, æ or ß, stays.
 * Punctuation counts as a space, so "Jan H." and "jan h" share a key, as do "Jan-Willem" and "Jan Willem".
 */
final readonly class LookupKey
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidName for invalid UTF-8, on which Normalizer fails, or for a name of only whitespace
     *                                  and punctuation
     */
    public static function fromName(string $name): self
    {
        if (!\mb_check_encoding($name, 'UTF-8')) {
            throw new InvalidName('A name must be valid UTF-8.');
        }

        $decomposed = Normalizer::normalize($name, Normalizer::FORM_D);
        \assert(\is_string($decomposed), 'Normalizer only fails on invalid UTF-8, which is refused above.');

        $bare = (string) \preg_replace(['/\p{Mn}/u', '/[\p{P}\s]+/u'], ['', ' '], $decomposed);
        $key = \mb_strtolower(\trim($bare));
        if ('' === $key) {
            throw new InvalidName('A name needs at least one character besides whitespace and punctuation.');
        }

        return new self($key);
    }
}
