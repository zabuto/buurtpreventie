<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\RuleViolation;

/**
 * The code of a rule violation: its short class name in snake case, so RoundIsFull is round_is_full. The code is the
 * key of the title in translations/problems.*.yaml and the end of the problem type.
 */
final readonly class ProblemCode
{
    /**
     * @param class-string<RuleViolation>|RuleViolation $violation
     */
    public static function of(string|RuleViolation $violation): string
    {
        $class = \is_string($violation) ? $violation : $violation::class;
        $shortName = \substr($class, (int) \strrpos($class, '\\') + 1);

        return \mb_strtolower((string) \preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
    }
}
