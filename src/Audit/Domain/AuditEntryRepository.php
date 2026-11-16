<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * Entries are only added, through the AuditTrail, each as the next link of the chain; the changes allowed are wiping
 * what an erased member leaves behind and pruning old entries, neither of which breaks the chain.
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
     * @return list<AuditEntry> the entries that match the filter, newest first
     */
    public function matching(AuditFilter $filter, int $offset, int $limit): array;

    public function countMatching(AuditFilter $filter): int;

    /**
     * Wipes the name of the member from every entry the member made; the id stays, so the actions stay attributed
     * to one "removed member".
     */
    public function eraseActorName(Uuid $memberId): void;

    /**
     * Wipes the changed fields of every entry about the member; their hash stays, so the chain holds.
     */
    public function eraseChangesAbout(Uuid $memberId): void;

    /**
     * Locks the head of the chain until the commit, so no other transaction links to the same entry, and returns it.
     * Called inside a transaction, as its last step.
     */
    public function lockHead(): ChainLink;

    /**
     * Stores an entry that is linked after the locked head and makes it the head.
     */
    public function append(AuditEntry $entry): void;

    public function head(): ChainLink;

    /**
     * Where checking the chain starts: the last pruned entry, or the start of the chain.
     */
    public function anchor(): ChainLink;

    /**
     * @return list<AuditEntry> at most $limit entries with a position above $position, in the order of their position
     */
    public function chainAfter(int $position, int $limit): array;

    /**
     * Counts the entries from before the chain, which have a position but no hash, up to and including $position.
     */
    public function countUnchainedThrough(int $position): int;

    /**
     * Removes every entry up to and including $position and moves the anchor to the last one removed, inside the
     * running transaction, so the chain stays checkable from there.
     *
     * @throws LogicException when no linked entry holds $position past the anchor
     */
    public function pruneThrough(int $position): void;
}
