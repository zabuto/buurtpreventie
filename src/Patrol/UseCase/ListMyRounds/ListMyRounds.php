<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListMyRounds;

final readonly class ListMyRounds
{
    /**
     * @param bool|null $walked true for the walked rounds only, false for every other round, null for all
     */
    public function __construct(
        public ?bool $walked = null,
    ) {
    }
}
