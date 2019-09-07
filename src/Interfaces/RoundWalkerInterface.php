<?php

namespace App\Interfaces;

/**
 * RoundWalkerInterface
 */
interface RoundWalkerInterface
{
    /**
     * @return RoundInterface
     */
    public function getRound();

    /**
     * @return WalkerInterface
     */
    public function getWalker();
}
