<?php

namespace App\Interfaces;

use DateTime;

/**
 * RoundInterface
 */
interface RoundInterface
{
    /**
     * @return DateTime
     */
    public function getDatetime();

    /**
     * @return MeetingPointInterface|null
     */
    public function getMeetingPoint();

    /**
     * @return RoundWalkerInterface[]
     */
    public function getWalkers();

    /**
     * @return RoundResultInterface[]
     */
    public function getResults();

    /**
     * @return CommentInterface[]
     */
    public function getComments();
}
