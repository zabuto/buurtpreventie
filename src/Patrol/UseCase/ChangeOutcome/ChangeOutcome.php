<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ChangeOutcome;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Only the name changes: the kind decides whether the reports filed under it keep a note.
 */
final readonly class ChangeOutcome
{
    public function __construct(
        #[Assert\Uuid]
        public string $outcomeId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
    ) {
    }
}
