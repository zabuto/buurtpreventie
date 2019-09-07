<?php

namespace App\Interfaces;

use DateTime;

/**
 * CommentInterface
 */
interface CommentInterface
{
    /**
     * @return RoundInterface
     */
    public function getRound();

    /**
     * @return DateTime
     */
    public function getCreatedAt();

    /**
     * @return WalkerInterface
     */
    public function getCreatedBy();

    /**
     * @return string
     */
    public function getMemo();
}
