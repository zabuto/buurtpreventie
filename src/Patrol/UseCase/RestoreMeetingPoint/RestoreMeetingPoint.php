<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\RestoreMeetingPoint;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RestoreMeetingPoint
{
    public function __construct(
        #[Assert\Uuid]
        public string $meetingPointId,
    ) {
    }
}
