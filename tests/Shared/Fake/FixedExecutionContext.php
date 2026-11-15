<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fake;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Channel;
use App\Shared\Domain\ExecutionContext;
use Override;

final readonly class FixedExecutionContext implements ExecutionContext
{
    public function __construct(
        private ?Actor $actor = null,
        private Channel $channel = Channel::Console,
    ) {
    }

    #[Override]
    public function channel(): Channel
    {
        return $this->channel;
    }

    #[Override]
    public function actor(): ?Actor
    {
        return $this->actor;
    }
}
