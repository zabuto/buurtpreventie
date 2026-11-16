<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeRound;

use App\Shared\Domain\Unchanged;
use Override;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\GroupSequenceProviderInterface;

/**
 * Changes when and where the round starts, as a JSON Merge Patch: a field left out stays, null clears the meeting
 * point or note.
 */
#[Assert\GroupSequenceProvider]
final readonly class ChangeRound implements GroupSequenceProviderInterface
{
    /**
     * @param string|Unchanged $startsAt ISO 8601 with offset
     */
    public function __construct(
        #[Assert\Uuid]
        public string $roundId,
        #[Assert\NotBlank(groups: ['startsAt'])]
        #[Assert\DateTime(format: \DATE_ATOM, groups: ['startsAt'])]
        public string|Unchanged $startsAt = Unchanged::Field,
        #[Assert\Uuid(groups: ['meetingPointId'])]
        public string|Unchanged|null $meetingPointId = Unchanged::Field,
        #[Assert\Length(max: 500, groups: ['startNote'])]
        public string|Unchanged|null $startNote = Unchanged::Field,
    ) {
    }

    /**
     * @return list<list<string>>
     */
    #[Override]
    #[Ignore]
    public function getGroupSequence(): array
    {
        return [[self::class, ...Unchanged::fieldsSetIn($this)]];
    }
}
