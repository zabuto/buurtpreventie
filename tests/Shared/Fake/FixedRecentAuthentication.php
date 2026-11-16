<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fake;

use App\Shared\Domain\RecentAuthentication;
use Override;

final readonly class FixedRecentAuthentication implements RecentAuthentication
{
    public function __construct(
        private bool $recent,
    ) {
    }

    #[Override]
    public function isRecent(): bool
    {
        return $this->recent;
    }
}
