<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\DeleteRound;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeleteRound
{
    public function __construct(
        #[Assert\Uuid]
        public string $roundId,
    ) {
    }
}
