<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeMember;

use App\Shared\Domain\Unchanged;
use Override;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\GroupSequenceProviderInterface;

/**
 * A JSON Merge Patch: a field left out stays, null clears the phone number or address.
 */
#[Assert\GroupSequenceProvider]
final readonly class ChangeMember implements GroupSequenceProviderInterface
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
        #[Assert\NotBlank(groups: ['email'])]
        #[Assert\Length(max: 180, groups: ['email'])]
        public string|Unchanged $email = Unchanged::Field,
        #[Assert\NotBlank(groups: ['name'])]
        #[Assert\Length(max: 100, groups: ['name'])]
        public string|Unchanged $name = Unchanged::Field,
        #[Assert\NotBlank(groups: ['displayName'])]
        #[Assert\Length(max: 100, groups: ['displayName'])]
        public string|Unchanged $displayName = Unchanged::Field,
        #[Assert\NotBlank(allowNull: true, groups: ['phone'])]
        #[Assert\Length(max: 30, groups: ['phone'])]
        public string|Unchanged|null $phone = Unchanged::Field,
        #[Assert\NotBlank(allowNull: true, groups: ['address'])]
        #[Assert\Length(max: 255, groups: ['address'])]
        public string|Unchanged|null $address = Unchanged::Field,
        public bool|Unchanged $certified = Unchanged::Field,
        /** On: the member uses MCP with every role, whichever roles the association allows there. */
        public bool|Unchanged $mcpAllowed = Unchanged::Field,
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
