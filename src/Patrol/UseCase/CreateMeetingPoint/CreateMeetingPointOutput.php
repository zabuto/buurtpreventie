<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateMeetingPoint;

final readonly class CreateMeetingPointOutput
{
    public function __construct(
        public string $id,
    ) {
    }
}
