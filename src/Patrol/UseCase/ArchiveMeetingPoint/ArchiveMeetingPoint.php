<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ArchiveMeetingPoint;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ArchiveMeetingPoint
{
    public function __construct(
        #[Assert\Uuid]
        public string $meetingPointId,
    ) {
    }
}
