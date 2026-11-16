<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;

/**
 * The settings shape what every member and visitor sees, and switch access with API tokens on or off: the admin
 * keeps them. What visitors may see of them is public through the configuration, without a vote.
 */
final readonly class SettingsPolicy
{
    public function mayView(Actor $actor): bool
    {
        return $actor->hasRole(Role::Admin);
    }

    public function mayChange(Actor $actor): bool
    {
        return $actor->hasRole(Role::Admin);
    }
}
