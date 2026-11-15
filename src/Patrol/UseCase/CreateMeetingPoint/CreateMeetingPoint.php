<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateMeetingPoint;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateMeetingPoint
{
    public function __construct(
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
