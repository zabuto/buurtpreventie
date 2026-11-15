<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\AddRoundComment;

final readonly class AddRoundCommentOutput
{
    public function __construct(
        public string $id,
    ) {
    }
}
