<?php declare(strict_types=1);

namespace App\Model;

use App\Entity\RoundResult;

final readonly class ResultsModel
{
    public function __construct(
        /* @var MetricModel[] */
        public array $metrics = [],
        /* @var RoundResult[] */
        public array $list = [],
    )
    {
    }
}
