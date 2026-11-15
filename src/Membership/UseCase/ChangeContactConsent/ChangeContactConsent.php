<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeContactConsent;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeContactConsent
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
        public bool $consent,
    ) {
    }
}
