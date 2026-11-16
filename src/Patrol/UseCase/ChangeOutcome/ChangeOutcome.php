<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeOutcome;

use App\Shared\Domain\Unchanged;
use Override;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\GroupSequenceProviderInterface;

/**
 * Only the name changes, as a JSON Merge Patch: the kind decides whether the reports filed under it keep a note.
 */
#[Assert\GroupSequenceProvider]
final readonly class ChangeOutcome implements GroupSequenceProviderInterface
{
    public function __construct(
        #[Assert\Uuid]
        public string $outcomeId,
        #[Assert\NotBlank(groups: ['name'])]
        #[Assert\Length(max: 100, groups: ['name'])]
        public string|Unchanged $name = Unchanged::Field,
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
