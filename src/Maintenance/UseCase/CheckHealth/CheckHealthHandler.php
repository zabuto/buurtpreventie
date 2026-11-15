<?php

declare(strict_types=1);

namespace App\Maintenance\UseCase\CheckHealth;

use App\Shared\Domain\ReadAction;

/**
 * Public endpoint: the answer carries no version or environment details, because those help an attacker pick
 * exploits.
 */
#[ReadAction]
final readonly class CheckHealthHandler
{
    public function __invoke(CheckHealth $input): CheckHealthOutput
    {
        return new CheckHealthOutput(status: 'ok');
    }
}
