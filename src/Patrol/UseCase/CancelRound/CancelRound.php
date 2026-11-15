<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CancelRound;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CancelRound
{
    public function __construct(
        #[Assert\Uuid]
        public string $roundId,
        #[Assert\Length(max: 500)]
        public ?string $reason = null,
    ) {
    }
}
