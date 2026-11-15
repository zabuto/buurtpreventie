<?php

declare(strict_types=1);

namespace App\Audit\UseCase\EraseActorName;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class EraseActorName
{
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
    ) {
    }
}
