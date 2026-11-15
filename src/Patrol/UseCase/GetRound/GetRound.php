<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\GetRound;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetRound
{
    public function __construct(
        #[Assert\Uuid]
        public string $roundId,
    ) {
    }
}
