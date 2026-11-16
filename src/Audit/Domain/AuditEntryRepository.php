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
     * @return list<AuditEntry> the entries of actions the member took, newest first
     */
    public function byActor(Uuid $memberId, int $offset, int $limit): array;

    /**
     * @return list<AuditEntry> the entries about one subject, such as a round, oldest first
     */
    public function bySubject(string $subjectType, Uuid $subjectId): array;

    /**
     * Wipes the name of the member from every entry the member made; the id stays, so the actions stay attributed
     * to one "removed member".
     */
    public function eraseActorName(Uuid $memberId): void;
}
