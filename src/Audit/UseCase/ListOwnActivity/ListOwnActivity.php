<?php

declare(strict_types=1);

namespace App\Audit\UseCase\ListOwnActivity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A page of what the member acting did, newest first.
 */
final readonly class ListOwnActivity
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
    ) {
    }
}
