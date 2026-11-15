<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateOutcome;

use App\Patrol\Domain\Catalogue\OutcomeKind;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOutcome
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
        #[Assert\Choice(callback: [OutcomeKind::class, 'values'])]
        public string $kind,
    ) {
    }
}
