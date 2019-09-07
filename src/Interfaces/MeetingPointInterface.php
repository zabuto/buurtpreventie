<?php

namespace App\Interfaces;

use App\Geo\ValueObject\Point;

/**
 * MeetingPointInterface
 */
interface MeetingPointInterface
{
    /**
     * @return string
     */
    public function getDescription();

    /**
     * @return Point|null
     */
    public function getLocation();
}
