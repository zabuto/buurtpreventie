<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ReportRoundResult;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReportRoundResult
{
    public function __construct(
        #[Assert\Uuid]
        public string $roundId,
        #[Assert\Uuid]
        public string $outcomeId,
        #[Assert\Length(max: 5000)]
        public ?string $note = null,
    ) {
    }
}
