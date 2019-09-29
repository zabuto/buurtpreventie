<?php declare(strict_types=1);

namespace App\Model;

use App\Interfaces\WalkerInterface;
use DateTime;

/**
 * WalkerDayModel
 */
class WalkerDayModel
{
    /**
     * @var WalkerInterface
     */
    private $walker;

    /**
     * @var DateTime
     */
    private $date;

    /**
     * @var WalkModel[]
     */
    private $walks;

    /**
     * @return WalkerInterface
     */
    public function getWalker(): WalkerInterface
    {
        return $this->walker;
    }

    /**
     * @param  WalkerInterface $walker
     */
    public function setWalker(WalkerInterface $walker): void
    {
        $this->walker = $walker;
    }

    /**
     * @return DateTime
     */
    public function getDate(): DateTime
    {
        return $this->date;
    }

    /**
     * @param  DateTime $date
     */
    public function setDate(DateTime $date): void
    {
        $this->date = $date;
    }

    /**
     * @return WalkModel[]
     */
    public function getWalks(): array
    {
        return $this->walks;
    }

    /**
     * @param  WalkModel $walk
     */
    public function addWalk(WalkModel $walk): void
    {
        $this->walks[] = $walk;
    }
}
