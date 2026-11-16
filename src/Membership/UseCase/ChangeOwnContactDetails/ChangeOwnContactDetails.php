<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeOwnContactDetails;

use App\Shared\Domain\Unchanged;
use Override;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\GroupSequenceProviderInterface;

/**
 * What a member changes for themselves, as a JSON Merge Patch: a field left out stays, null removes a phone number
 * or address.
 */
#[Assert\GroupSequenceProvider]
final readonly class ChangeOwnContactDetails implements GroupSequenceProviderInterface
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true, groups: ['phone'])]
        #[Assert\Length(max: 30, groups: ['phone'])]
        public string|Unchanged|null $phone = Unchanged::Field,
        #[Assert\NotBlank(allowNull: true, groups: ['address'])]
        #[Assert\Length(max: 255, groups: ['address'])]
        public string|Unchanged|null $address = Unchanged::Field,
        public bool|Unchanged $consentContactShare = Unchanged::Field,
        public bool|Unchanged $notifyOpenRounds = Unchanged::Field,
        /** Empty for the member acting. */
        #[Assert\Uuid]
        public ?string $memberId = null,
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
