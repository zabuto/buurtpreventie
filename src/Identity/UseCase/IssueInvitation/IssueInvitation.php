<?php

declare(strict_types=1);

namespace App\Identity\UseCase\IssueInvitation;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class IssueInvitation
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
    ) {
    }
}
