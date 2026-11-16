<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetApiTokenAccess;

final readonly class GetApiTokenAccessOutput
{
    /**
     * @param bool $allowed false refuses every API token and every new one
     */
    public function __construct(
        public bool $allowed,
    ) {
    }
}
