<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ArchiveOutcome;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ArchiveOutcome
{
    public function __construct(
        #[Assert\Uuid]
        public string $outcomeId,
    ) {
    }
}
