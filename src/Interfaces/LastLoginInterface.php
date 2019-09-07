<?php

namespace App\Interfaces;

use DateTime;

/**
 * LastLoginInterface
 */
interface LastLoginInterface
{
    /**
     * @param  DateTime $date
     */
    public function setLastLogin(DateTime $date);
}
