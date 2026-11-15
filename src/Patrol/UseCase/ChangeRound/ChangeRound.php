<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeRound;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Replaces when and where the round starts; a field left empty clears it.
 */
final readonly class ChangeRound
{
    /**
     * @param string $startsAt ISO 8601 with offset
     */
    public function __construct(
        #[Assert\Uuid]
        public string $roundId,
        #[Assert\NotBlank]
        #[Assert\DateTime(format: \DATE_ATOM)]
        public string $startsAt,
        #[Assert\Uuid]
        public ?string $meetingPointId = null,
        #[Assert\Length(max: 500)]
        public ?string $startNote = null,
    ) {
    }
}
