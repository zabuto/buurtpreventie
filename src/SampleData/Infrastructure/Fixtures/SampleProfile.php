<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use App\Shared\Domain\Role;

final readonly class SampleProfile
{
    /**
     * @param list<Role> $roles
     */
    public function __construct(
        public string $email,
        public string $name,
        public string $displayName,
        public string $phone,
        public array $roles = [Role::Walker],
    ) {
    }
}
