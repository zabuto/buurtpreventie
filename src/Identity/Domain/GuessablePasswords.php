<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use SensitiveParameter;

/**
 * Whether a password is easy to guess: a common password, the member's own name or e-mail address, or too simple for
 * the strength meter. Capitals and spaces do not count, so "Welkom 123456" is as common as "welkom123456".
 */
final readonly class GuessablePasswords
{
    /**
     * A name part this short sits in too many words ("Jan" in "januari") and makes a passphrase no easier to guess.
     */
    private const int SHORTEST_PERSONAL_WORD = 4;

    public function __construct(
        private CommonPasswords $commonPasswords,
        private PasswordStrengthMeter $strengthMeter,
    ) {
    }

    /**
     * @param list<string> $personalDetails names and e-mail addresses of the member
     */
    public function isGuessable(#[SensitiveParameter] string $password, array $personalDetails): bool
    {
        return (
            $this->commonPasswords->contains(self::normalise($password))
                || self::holdsPersonalWord($password, $personalDetails)
                || !$this->strengthMeter->isStrongEnough($password)
        );
    }

    private static function normalise(#[SensitiveParameter] string $password): string
    {
        return \mb_strtolower((string) \preg_replace('/\s+/u', '', $password));
    }

    /**
     * @param list<string> $personalDetails
     */
    private static function holdsPersonalWord(#[SensitiveParameter] string $password, array $personalDetails): bool
    {
        $letters = self::lettersAndDigits($password);
        foreach ($personalDetails as $detail) {
            foreach (self::personalWords($detail) as $word) {
                if (\str_contains($letters, $word)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The whole name or the part of the address before the @, and each part of it on its own.
     *
     * @return list<string>
     */
    private static function personalWords(string $detail): array
    {
        $local = \explode('@', \mb_strtolower($detail), 2)[0];
        $parts = \preg_split('/[^\p{L}\p{N}]+/u', $local, flags: \PREG_SPLIT_NO_EMPTY);
        if (false === $parts) {
            return [];
        }

        return \array_values(\array_filter(
            [\implode('', $parts), ...$parts],
            static fn(string $word): bool => \mb_strlen($word) >= self::SHORTEST_PERSONAL_WORD,
        ));
    }

    private static function lettersAndDigits(#[SensitiveParameter] string $password): string
    {
        return (string) \preg_replace('/[^\p{L}\p{N}]+/u', '', \mb_strtolower($password));
    }
}
