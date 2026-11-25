<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\TokenLimits;
use App\Identity\Infrastructure\Security\TokenLimitTrace;
use App\Shared\Domain\AuditedAction;
use App\Tests\Identity\Fake\ApiTokenFixture;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Shared\Fake\RecordingNotifier;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Uid\Uuid;

/**
 * The daily limit with small numbers: two thousand requests through the kernel would make the suite slow.
 */
final class TokenLimitsTest extends TestCase
{
    private InMemoryApiTokenRepository $tokens;

    private RecordingAuditTrail $auditTrail;

    private string $tokenId;

    protected function setUp(): void
    {
        $this->tokens = new InMemoryApiTokenRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->tokenId = ApiTokenFixture::stored($this->tokens, Uuid::v7())->id()->toRfc4122();
    }

    #[Test]
    public function it_refuses_a_read_above_the_daily_limit_while_the_minute_limit_has_room(): void
    {
        $limits = $this->limits(readsPerMinute: 60, readsPerDay: 2);
        $limits->read($this->tokenId);
        $limits->read($this->tokenId);

        $this->expectException(TooManyRequestsHttpException::class);

        $limits->read($this->tokenId);
    }

    #[Test]
    public function it_traces_a_token_that_reaches_the_daily_limit(): void
    {
        $limits = $this->limits(readsPerMinute: 60, readsPerDay: 1);
        $limits->read($this->tokenId);

        self::refused(fn() => $limits->read($this->tokenId));

        self::assertSame(['api_token.limit_reached'], \array_map(static fn(AuditedAction $action): string => $action->action, $this->auditTrail->recorded));
    }

    #[Test]
    public function it_leaves_no_trace_of_a_token_that_reaches_only_the_minute_limit(): void
    {
        $limits = $this->limits(readsPerMinute: 1, readsPerDay: 60);
        $limits->read($this->tokenId);

        self::refused(fn() => $limits->read($this->tokenId));

        self::assertSame([], $this->auditTrail->recorded);
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function it_counts_writes_apart_from_reads(): void
    {
        $limits = $this->limits(readsPerMinute: 1, readsPerDay: 1);
        $limits->read($this->tokenId);

        $limits->write($this->tokenId);
    }

    /**
     * @param callable(): void $request
     */
    private static function refused(callable $request): void
    {
        try {
            $request();
        } catch (TooManyRequestsHttpException $exception) {
            self::assertSame(429, $exception->getStatusCode());

            return;
        }

        self::fail('The request was accepted.');
    }

    private function limits(int $readsPerMinute, int $readsPerDay): TokenLimits
    {
        return new TokenLimits(
            self::factory('reads_per_minute', $readsPerMinute, '1 minute'),
            self::factory('reads_per_day', $readsPerDay, '1 day'),
            self::factory('writes_per_hour', 20, '1 hour'),
            new TokenLimitTrace(
                $this->tokens,
                $this->auditTrail,
                new RecordingNotifier(),
                new ImmediateTransaction(),
                new MockClock('2026-11-16T10:00:00+00:00'),
                new ArrayAdapter(),
            ),
        );
    }

    private static function factory(string $id, int $limit, string $interval): RateLimiterFactory
    {
        return new RateLimiterFactory(['id' => $id, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => $interval], new InMemoryStorage());
    }
}
