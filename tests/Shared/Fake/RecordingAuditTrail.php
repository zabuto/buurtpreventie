<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fake;

use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Channel;
use App\Shared\Domain\ExecutionContext;
use Override;

/**
 * Keeps every action it is asked to record; in a kernel also the channel it came in through, which the real trail
 * takes from the same execution context.
 */
final class RecordingAuditTrail implements AuditTrail
{
    /** @var list<AuditedAction> */
    public array $recorded = [];

    /** @var list<Channel> */
    public array $channels = [];

    public function __construct(
        private readonly ?ExecutionContext $context = null,
    ) {
    }

    #[Override]
    public function record(AuditedAction $action): void
    {
        $this->recorded[] = $action;
        if (null !== $this->context) {
            $this->channels[] = $this->context->channel();
        }
    }
}
