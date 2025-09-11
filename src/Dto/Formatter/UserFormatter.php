<?php declare(strict_types=1);

namespace App\Dto\Formatter;

use App\Entity\User;

final class UserFormatter
{
    public static function name(?object $value, object $source): ?string
    {
        if ($value instanceof User) {
            return $value->getName();
        }

        return null;
    }

    public static function email(?object $value, object $source): ?string
    {
        if ($value instanceof User) {
            return $value->getEmail();
        }

        return null;
    }
}
