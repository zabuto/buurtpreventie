<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeMeetingPoint;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Replaces every field: leaving out both coordinates clears them.
 */
final readonly class ChangeMeetingPoint
{
    public function __construct(
        #[Assert\Uuid]
        public string $meetingPointId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
        #[Assert\Range(min: -90, max: 90)]
        public ?float $latitude,
        #[Assert\Range(min: -180, max: 180)]
        public ?float $longitude,
    ) {
    }
}
