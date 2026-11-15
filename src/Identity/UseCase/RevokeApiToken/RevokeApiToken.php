<?php

declare(strict_types=1);

namespace App\Identity\UseCase\RevokeApiToken;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RevokeApiToken
{
    public function __construct(
        #[Assert\Uuid]
        public string $tokenId,
    ) {
    }
}
