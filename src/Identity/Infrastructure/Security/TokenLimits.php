<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Counts the reads and writes of one API token against the limits in rate_limiter.yaml. The key is the id of the
 * token, never its secret, and every path a token takes shares the count.
 */
final readonly class TokenLimits
{
    public function __construct(
        #[Target('token_reads_per_minute')]
        private RateLimiterFactoryInterface $readsPerMinute,
        #[Target('token_reads_per_day')]
        private RateLimiterFactoryInterface $readsPerDay,
        #[Target('token_writes_per_hour')]
        private RateLimiterFactoryInterface $writesPerHour,
        private TokenLimitTrace $trace,
    ) {
    }

    /**
     * @throws TooManyRequestsHttpException with the seconds until the next read is accepted
     */
    public function read(string $tokenId): void
    {
        self::deny($this->readsPerMinute->create($tokenId)->consume());

        $daily = $this->readsPerDay->create($tokenId)->consume();
        if (!$daily->isAccepted()) {
            $this->trace->dailyLimitReached($tokenId);
        }

        self::deny($daily);
    }

    /**
     * @throws TooManyRequestsHttpException with the seconds until the next write is accepted
     */
    public function write(string $tokenId): void
    {
        self::deny($this->writesPerHour->create($tokenId)->consume());
    }

    private static function deny(RateLimit $limit): void
    {
        if ($limit->isAccepted()) {
            return;
        }

        throw new TooManyRequestsHttpException(\max(1, $limit->getRetryAfter()->getTimestamp() - \time()));
    }
}
