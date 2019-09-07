<?php

namespace App\Interfaces;

use DateTime;

/**
 * UserActivationInterface
 */
interface UserTokenInterface
{
    /**
     * @return string|null
     */
    public function getToken();

    /**
     * @return DateTime|null
     */
    public function getTokenValidUntil();
}
