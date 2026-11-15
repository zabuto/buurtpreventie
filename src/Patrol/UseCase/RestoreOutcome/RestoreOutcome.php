<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\RestoreOutcome;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RestoreOutcome
{
    public function __construct(
        #[Assert\Uuid]
        public string $outcomeId,
    ) {
    }
}
