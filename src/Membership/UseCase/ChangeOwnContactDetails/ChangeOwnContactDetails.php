<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeOwnContactDetails;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a member changes for themselves. A field left out, or null, stays as it is; empty text removes a phone number
 * or address.
 */
final readonly class ChangeOwnContactDetails
{
    public function __construct(
        #[Assert\Length(max: 30)]
        public ?string $phone = null,
        #[Assert\Length(max: 255)]
        public ?string $address = null,
        public ?bool $consentContactShare = null,
        public ?bool $notifyOpenRounds = null,
        /** Empty for the member acting. */
        #[Assert\Uuid]
        public ?string $memberId = null,
    ) {
    }
}
