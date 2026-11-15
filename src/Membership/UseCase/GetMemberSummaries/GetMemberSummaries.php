<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetMemberSummaries;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetMemberSummaries
{
    public function __construct(
        #[Assert\Length(max: 100)]
        public ?string $search = null,
    ) {
    }
}
