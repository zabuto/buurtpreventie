<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * For an input constraint that takes its choices from the enum, as an attribute cannot call cases() itself.
 */
trait BackedEnumValues
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_map(static fn(self $case): string => $case->value, self::cases());
    }
}
