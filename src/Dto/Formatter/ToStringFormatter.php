<?php declare(strict_types=1);

namespace App\Dto\Formatter;

use Stringable;

final class ToStringFormatter
{
    public static function format(?object $value, object $source): ?string
    {
        if ($value instanceof Stringable) {
            return (string)$value;
        }

        return null;
    }
}
