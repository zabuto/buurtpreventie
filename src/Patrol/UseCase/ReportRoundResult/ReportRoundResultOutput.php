<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ReportRoundResult;

final readonly class ReportRoundResultOutput
{
    public function __construct(
        public string $id,
    ) {
    }
}
