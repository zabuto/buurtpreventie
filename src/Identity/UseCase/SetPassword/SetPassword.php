<?php

declare(strict_types=1);

namespace App\Identity\UseCase\SetPassword;

use SensitiveParameter;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetPassword
{
    public function __construct(
        #[Assert\Length(min: 15)]
        // Symfony's password hashers refuse more than 4,096 bytes, which fewer characters can reach.
        #[Assert\Length(max: 4096, countUnit: Assert\Length::COUNT_BYTES)]
        #[SensitiveParameter]
        public string $password,
        /** Empty for the member acting. */
        #[Assert\Uuid]
        public ?string $memberId = null,
    ) {
    }
}
