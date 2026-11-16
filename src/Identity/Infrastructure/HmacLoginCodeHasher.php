<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Domain\LoginCodeHasher;
use Override;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * HMAC-SHA-256 with APP_SECRET over challenge id and code.
 */
final readonly class HmacLoginCodeHasher implements LoginCodeHasher
{
    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        #[SensitiveParameter]
        private string $key,
    ) {
    }

    #[Override]
    public function hash(string $challengeId, #[SensitiveParameter] string $code): string
    {
        return \hash_hmac('sha256', $challengeId . ':' . $code, $this->key, binary: true);
    }
}
