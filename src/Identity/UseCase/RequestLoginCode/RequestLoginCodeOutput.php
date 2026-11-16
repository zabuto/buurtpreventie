<?php

declare(strict_types=1);

namespace App\Identity\UseCase\RequestLoginCode;

final readonly class RequestLoginCodeOutput
{
    /**
     * @param string $challengeId kept by the browser that asked, and sent along with the code
     */
    public function __construct(
        public string $challengeId,
    ) {
    }
}
