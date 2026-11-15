<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\SignUpForRound;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SignUpForRound
{
    /**
     * @param string|null $memberId the member to sign up; the actor when null
     */
    public function __construct(
        #[Assert\Uuid]
        public string $roundId,
        #[Assert\Uuid]
        public ?string $memberId = null,
    ) {
    }
}
