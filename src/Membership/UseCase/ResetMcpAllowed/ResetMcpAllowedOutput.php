<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ResetMcpAllowed;

final readonly class ResetMcpAllowedOutput
{
    /**
     * @param int $membersReset the members switched off; 0 when nobody was picked
     */
    public function __construct(
        public int $membersReset,
    ) {
    }
}
