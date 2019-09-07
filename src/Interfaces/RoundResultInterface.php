<?php

namespace App\Interfaces;

/**
 * RoundResultInterface
 */
interface RoundResultInterface
{
    /**
     * @return RoundInterface
     */
    public function getRound();

    /**
     * @return ResultInterface
     */
    public function getResult();

    /**
     * @return string|null
     */
    public function getMemo();
}
