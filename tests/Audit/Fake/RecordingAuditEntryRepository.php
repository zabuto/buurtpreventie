<?php

declare(strict_types=1);

namespace App\Tests\Audit\Fake;

use App\Audit\Domain\AuditEntryRepository;
use Override;
use Symfony\Component\Uid\Uuid;

/**
 * Records whose name was erased; the bulk update itself has its own database test.
 */
final class RecordingAuditEntryRepository implements AuditEntryRepository
{
    /** @var list<Uuid> */
    public array $erasedActorNames = [];

    #[Override]
    public function eraseActorName(Uuid $memberId): void
    {
        $this->erasedActorNames[] = $memberId;
    }
}
