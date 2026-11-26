<?php

declare(strict_types=1);

namespace App\Identity\UseCase\SetPassword;

use OpenApi\Attributes as OA;
use SensitiveParameter;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetPassword
{
    public function __construct(
        #[OA\Property(
            description: 'At least 15 characters. Refused as password_too_weak when it is on the list of common '
                . 'passwords, holds the name of the member or the part of the e-mail address before the @ (capitals and spaces '
                . 'do not count), or is too simple. A few unrelated words, such as "fiets lamp regen", pass.',
        )]
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
