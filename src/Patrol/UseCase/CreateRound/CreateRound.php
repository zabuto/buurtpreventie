<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateRound;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateRound
{
    /**
     * @param string $startsAt ISO 8601 with offset
     * @param string|null $firstWalkerId the member who walks and owns the round; the actor when null
     * @param string|null $comment a first comment on the round by the actor
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\DateTime(format: \DATE_ATOM)]
        public string $startsAt,
        #[Assert\Uuid]
        public ?string $firstWalkerId = null,
        #[Assert\Uuid]
        public ?string $meetingPointId = null,
        #[Assert\Length(max: 500)]
        public ?string $startNote = null,
        #[Assert\Length(max: 2000)]
        public ?string $comment = null,
    ) {
    }
}
