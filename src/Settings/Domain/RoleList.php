<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use App\Shared\Domain\Role;

/**
 * A setting that lists roles, stored in alphabetical order, each once, joined by commas.
 */
final readonly class RoleList
{
    /**
     * @return list<string> the roles of a stored list, none for empty text
     */
    public static function of(string $stored): array
    {
        return '' === $stored ? [] : \explode(',', $stored);
    }

    /**
     * An empty list stores as empty text.
     *
     * @param string|int|bool|list<string> $roles
     *
     * @throws InvalidSetting for a value that is no list or a role that does not exist
     */
    public static function tidy(SettingName $name, string|int|bool|array $roles): string
    {
        if (!\is_array($roles) || [] !== \array_diff($roles, Role::values())) {
            throw new InvalidSetting($name->value, \sprintf('%s lists only roles from %s.', $name->value, \implode(', ', Role::values())));
        }

        $roles = \array_unique($roles);
        \sort($roles);

        return \implode(',', $roles);
    }
}
