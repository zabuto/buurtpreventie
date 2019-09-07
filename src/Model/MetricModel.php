<?php

namespace App\Model;

/**
 * MetricModel
 */
class MetricModel
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string|null
     */
    private $class;

    /**
     * @var int
     */
    private $count = 0;

    /**
     * @var float
     */
    private $percentage = 0.00;

    /**
     * @param  int    $id
     * @param  string $description
     */
    public function __construct(int $id, string $description)
    {
        $this->id = $id;
        $this->description = $description;
    }

    /**
     * @param  int $total
     */
    public function add(int $total): void
    {
        $this->count++;
        if ($total === 0 || $this->count === 0) {
            $this->percentage = 0;
        } else {
            $this->percentage = round(($this->count / $total) * 100);
        }
    }

    /**
     * @param  string|null $class
     */
    public function setClass(?string $class): void
    {
        $this->class = $class;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string|null
     */
    public function getClass(): ?string
    {
        return $this->class;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @return float
     */
    public function getPercentage(): float
    {
        return $this->percentage;
    }
}
