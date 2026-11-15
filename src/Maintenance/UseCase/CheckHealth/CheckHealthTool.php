<?php

declare(strict_types=1);

namespace App\Maintenance\UseCase\CheckHealth;

final readonly class CheckHealthTool
{
    public function __construct(
        private CheckHealthHandler $handler,
    ) {
    }

    public function __invoke(CheckHealth $input): CheckHealthOutput
    {
        return ($this->handler)($input);
    }
}
