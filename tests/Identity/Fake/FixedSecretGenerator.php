<?php

declare(strict_types=1);

namespace App\Tests\Identity\Fake;

use App\Identity\Domain\SecretGenerator;
use Override;
use SensitiveParameter;

final readonly class FixedSecretGenerator implements SecretGenerator
{
    public function __construct(
        // @mago-expect lint:no-literal-password - a made-up secret the tests compare with
        #[SensitiveParameter]
        private string $secret = 'bpt_vast-geheim',
    ) {
    }

    #[Override]
    public function newSecret(): string
    {
        return $this->secret;
    }
}
