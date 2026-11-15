<?php

declare(strict_types=1);

namespace App\Tests\Identity\Fake;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * A read token issued on 1 November 2026 that never expires.
 */
final readonly class ApiTokenFixture
{
    public static function stored(InMemoryApiTokenRepository $tokens, Uuid $memberId): ApiToken
    {
        $token = ApiToken::issue(
            $memberId,
            new TokenTerms('Script', [TokenScope::Read]),
            'bpt_' . \bin2hex(\random_bytes(8)),
            new DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        );
        $tokens->add($token);

        return $token;
    }
}
