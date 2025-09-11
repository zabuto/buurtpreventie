<?php declare(strict_types=1);

namespace App\Model;

final class MetricModel
{
    public function __construct(
        public int     $id,
        public string  $description,
        public int     $count = 0,
        public float   $percentage = 0.00,
        public ?string $class = null,
    )
    {
    }

    public function add(int $total): void
    {
        $this->count++;
        if ($total === 0 || $this->count === 0) {
            $this->percentage = 0;
        } else {
            $this->percentage = round(($this->count / $total) * 100);
        }
    }
}
