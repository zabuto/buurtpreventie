<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListRounds;

use App\Patrol\Domain\RoundStatus;
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
        #[Assert\Choice(callback: [RoundStatus::class, 'values'])]
        public ?string $status = null,
    ) {
    }
}
