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
     * No role at all is refused: a setting that would shut every role out has a switch of its own, such as mcpAccess.
     *
     * @param string|int|bool|list<string> $roles
     *
     * @throws InvalidSetting for a value that is no list, an empty list or a role that does not exist
     */
    public static function tidy(SettingName $name, string|int|bool|array $roles): string
    {
        if (!\is_array($roles) || [] === $roles || [] !== \array_diff($roles, Role::values())) {
            throw new InvalidSetting($name->value, \sprintf('%s lists one or more of %s.', $name->value, \implode(', ', Role::values())));
        }

        $roles = \array_unique($roles);
        \sort($roles);

        return \implode(',', $roles);
    }
}
