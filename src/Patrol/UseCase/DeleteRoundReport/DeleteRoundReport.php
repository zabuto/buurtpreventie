<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\DeleteRoundReport;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeleteRoundReport
{
    public function __construct(
        #[Assert\Uuid]
        public string $reportId,
    ) {
    }
}
