<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * Entries are only added, through the AuditTrail; the one change allowed is wiping the name of an erased member.
 */
interface AuditEntryRepository
{
    /**
     * Wipes the name of the member from every entry the member made; the id stays, so the actions stay attributed
     * to one "removed member".
     */
    public function eraseActorName(Uuid $memberId): void;
}
