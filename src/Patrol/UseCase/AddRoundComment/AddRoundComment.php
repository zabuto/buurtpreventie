<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\AddRoundComment;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddRoundComment
{
    public function __construct(
        #[Assert\Uuid]
        public string $roundId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 2000)]
        public string $text,
    ) {
    }
}
