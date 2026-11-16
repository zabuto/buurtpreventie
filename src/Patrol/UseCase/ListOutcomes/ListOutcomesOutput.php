<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListOutcomes;

final readonly class ListOutcomesOutput
{
    /**
     * @param list<array{id: string, name: string, kind: string, archived: bool}> $outcomes by name
     */
    public function __construct(
        public array $outcomes,
    ) {
    }
}
