<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\TokenLimits;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * The daily limit with small numbers: two thousand requests through the kernel would make the suite slow.
 */
final class TokenLimitsTest extends TestCase
{
    private const string TOKEN_ID = '019a2b3c-0000-7000-8000-000000000001';

    #[Test]
    public function it_refuses_a_read_above_the_daily_limit_while_the_minute_limit_has_room(): void
    {
        $limits = self::limits(readsPerMinute: 60, readsPerDay: 2);
        $limits->read(self::TOKEN_ID);
        $limits->read(self::TOKEN_ID);

        $this->expectException(TooManyRequestsHttpException::class);

        $limits->read(self::TOKEN_ID);
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function it_counts_writes_apart_from_reads(): void
    {
        $limits = self::limits(readsPerMinute: 1, readsPerDay: 1);
        $limits->read(self::TOKEN_ID);

        $limits->write(self::TOKEN_ID);
    }

    private static function limits(int $readsPerMinute, int $readsPerDay): TokenLimits
    {
        return new TokenLimits(
            self::factory('reads_per_minute', $readsPerMinute, '1 minute'),
            self::factory('reads_per_day', $readsPerDay, '1 day'),
            self::factory('writes_per_hour', 20, '1 hour'),
        );
    }

    private static function factory(string $id, int $limit, string $interval): RateLimiterFactory
    {
        return new RateLimiterFactory(['id' => $id, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => $interval], new InMemoryStorage());
    }
}
