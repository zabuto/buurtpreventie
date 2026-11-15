<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\CreateOutcome;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOutcome
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
        #[Assert\Choice(choices: ['nothing_to_report', 'remarks', 'incident'])]
        public string $kind,
    ) {
    }
}
