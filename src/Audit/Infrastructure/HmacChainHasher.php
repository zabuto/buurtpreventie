<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure;

use App\Audit\Domain\ChainHasher;
use LogicException;
use Override;
use SensitiveParameter;

/**
 * HMAC-SHA-256 with AUDIT_SECRET over the fields as compact JSON. A key of its own, so replacing APP_SECRET leaves
 * the chain intact; a plain hash would let anyone with a list of names recover erased changes from their hash.
 */
final readonly class HmacChainHasher implements ChainHasher
{
    /**
     * @throws LogicException without AUDIT_SECRET, as an entry without a fitting hash breaks the chain
     */
    public function __construct(
        #[SensitiveParameter]
        private string $key,
    ) {
        if ('' === $key) {
            throw new LogicException('The audit log needs AUDIT_SECRET; set it in .env.local or shared/.env.local.');
        }
    }

    #[Override]
    public function hash(array $fields): string
    {
        return \hash_hmac(
            'sha256',
            \json_encode($fields, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            $this->key,
            binary: true,
        );
    }
}
