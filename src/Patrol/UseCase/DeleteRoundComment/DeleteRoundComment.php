<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\DeleteRoundComment;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeleteRoundComment
{
    public function __construct(
        #[Assert\Uuid]
        public string $commentId,
    ) {
    }
}
