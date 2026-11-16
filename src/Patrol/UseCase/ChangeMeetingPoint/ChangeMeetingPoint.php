<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeMeetingPoint;

use App\Shared\Domain\Unchanged;
use Override;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\GroupSequenceProviderInterface;

/**
 * A JSON Merge Patch: a field left out stays. Latitude and longitude go as a pair: both left out, both null to
 * clear the position, or both a number.
 */
#[Assert\GroupSequenceProvider]
final readonly class ChangeMeetingPoint implements GroupSequenceProviderInterface
{
    public function __construct(
        #[Assert\Uuid]
        public string $meetingPointId,
        #[Assert\NotBlank(groups: ['name'])]
        #[Assert\Length(max: 100, groups: ['name'])]
        public string|Unchanged $name = Unchanged::Field,
        #[Assert\Range(min: -90, max: 90, groups: ['latitude'])]
        public float|Unchanged|null $latitude = Unchanged::Field,
        #[Assert\Range(min: -180, max: 180, groups: ['longitude'])]
        public float|Unchanged|null $longitude = Unchanged::Field,
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
