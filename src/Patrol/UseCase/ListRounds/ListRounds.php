<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListRounds;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListRounds
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\DateTime(format: \DATE_ATOM)]
        public string $from,
        #[Assert\NotBlank]
        #[Assert\DateTime(format: \DATE_ATOM)]
        public string $to,
        #[Assert\Choice(choices: ['seeking_buddy', 'staffed', 'walked', 'not_walked', 'cancelled'])]
        public ?string $status = null,
    ) {
    }
}
