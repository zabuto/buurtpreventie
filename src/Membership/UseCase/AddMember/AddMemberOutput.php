<?php

declare(strict_types=1);

namespace App\Membership\UseCase\AddMember;

final readonly class AddMemberOutput
{
    public function __construct(
        public string $id,
    ) {
    }
}
