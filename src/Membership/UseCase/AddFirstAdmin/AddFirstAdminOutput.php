<?php

declare(strict_types=1);

namespace App\Membership\UseCase\AddFirstAdmin;

final readonly class AddFirstAdminOutput
{
    public function __construct(
        public string $id,
    ) {
    }
}
