<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fake;

use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use Override;

final class RecordingAuditTrail implements AuditTrail
{
    /** @var list<AuditedAction> */
    public array $recorded = [];

    #[Override]
    public function record(AuditedAction $action): void
    {
        $this->recorded[] = $action;
    }
}
