<?php

declare(strict_types=1);

namespace App\Identity\UseCase\CreateApiToken;

use SensitiveParameter;

final readonly class CreateApiTokenOutput
{
    /**
     * @param string $token the secret, shown this once and never stored
     */
    public function __construct(
        public string $id,
        #[SensitiveParameter]
        public string $token,
    ) {
    }
}
