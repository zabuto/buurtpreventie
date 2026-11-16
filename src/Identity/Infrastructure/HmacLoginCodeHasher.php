<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Domain\LoginCodeHasher;
use LogicException;
use Override;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * HMAC-SHA-256 with APP_SECRET over challenge id and code.
 */
final readonly class HmacLoginCodeHasher implements LoginCodeHasher
{
    /**
     * @throws LogicException without APP_SECRET, which would leave a stored code as easy to recover as a plain hash
     */
    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        #[SensitiveParameter]
        private string $key,
    ) {
        if ('' === $key) {
            throw new LogicException('Login codes need APP_SECRET; set it in .env.local or shared/.env.local.');
        }
    }

    #[Override]
    public function hash(string $challengeId, #[SensitiveParameter] string $code): string
    {
        return \hash_hmac('sha256', $challengeId . ':' . $code, $this->key, binary: true);
    }
}
