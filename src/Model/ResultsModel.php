<?php

namespace App\Model;

use App\Entity\RoundResult;

/**
 * ResultsModel
 */
class ResultsModel
{
    /**
     * @var array
     */
    private $metrics = [];

    /**
     * @var RoundResult[]
     */
    private $list = [];

    /**
     * @return array
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * @param  array $metrics
     */
    public function setMetrics(array $metrics): void
    {
        $this->metrics = $metrics;
    }

    /**
     * @return RoundResult[]
     */
    public function getList(): array
    {
        return $this->list;
    }

    /**
     * @param  RoundResult[] $list
     */
    public function setList(array $list): void
    {
        $this->list = $list;
    }
}
