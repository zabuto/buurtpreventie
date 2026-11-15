<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeOpenRoundNotifications;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeOpenRoundNotifications
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
        public bool $notify,
    ) {
    }
}
