<?php

declare(strict_types=1);

namespace App\Audit\UseCase\GetRoundHistory;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetRoundHistory
{
    public function __construct(
        #[Assert\Uuid]
        public string $roundId,
    ) {
    }
}
