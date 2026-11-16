<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ListMembers;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListMembers
{
    public function __construct(
        #[Assert\Length(max: 100)]
        public ?string $search = null,
    ) {
    }
}
