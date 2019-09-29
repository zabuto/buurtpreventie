<?php declare(strict_types=1);

namespace App\Model;

use App\Interfaces\RoundInterface;

/**
 * WalkModel
 */
class WalkModel
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var RoundInterface
     */
    private $round;

    /**
     * @var string
     */
    private $timeofday;

    /**
     * @var bool
     */
    private $minimum;

    /**
     * @param  int            $id
     * @param  RoundInterface $round
     * @param  string         $timeofday
     * @param  bool           $minimum
     */
    public function __construct(int $id, RoundInterface $round, string $timeofday, bool $minimum)
    {
        $this->id = $id;
        $this->round = $round;
        $this->timeofday = $timeofday;
        $this->minimum = $minimum;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return RoundInterface
     */
    public function getRound(): RoundInterface
    {
        return $this->round;
    }

    /**
     * @return string
     */
    public function getDate(): string
    {
        return $this->getRound()->getDatetime()->format('Y-m-d');
    }

    /**
     * @return string
     */
    public function getTime(): string
    {
        return $this->getRound()->getDatetime()->format('H:i');
    }

    /**
     * @return string
     */
    public function getTimeOfDay(): string
    {
        return $this->timeofday;
    }

    /**
     * @return bool
     */
    public function hasMinimum(): bool
    {
        return $this->minimum;
    }
}
