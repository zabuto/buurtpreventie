<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\NotFound;
use Symfony\Component\Uid\Uuid;

interface MemberRepository
{
    /**
     * @throws NotFound
     */
    public function get(Uuid $id): Member;

    /**
     * Writes the new member within the running transaction, so a clash with another member surfaces here.
     *
     * @throws EmailAddressTaken when another member committed the same address in the meantime
     * @throws DisplayNameTaken when another member committed the same display name in the meantime
     */
    public function add(Member $member): void;

    /**
     * Writes the changes to the member within the running transaction, so a clash with another member surfaces here.
     *
     * @throws EmailAddressTaken when another member committed the same address in the meantime
     * @throws DisplayNameTaken when another member committed the same display name in the meantime
     */
    public function update(Member $member): void;

    /**
     * @param list<Uuid> $ids
     *
     * @return list<Member> the members found; an unknown id is skipped
     */
    public function getMany(array $ids): array;

    /**
     * Whatever their roles; the caller picks the walkers.
     *
     * @return list<Member> the active members who want to hear about rounds that seek a buddy
     */
    public function activeWithOpenRoundNotifications(): array;

    /**
     * Locked for writing until the running transaction ends, so two admins taking each other's role at the same
     * moment cannot both see the other as the admin who remains.
     *
     * @return list<Member>
     */
    public function activeAdminsForUpdate(): array;

    /**
     * @return Member|null null when no member has the address, which an erased member never has
     */
    public function ofEmail(EmailAddress $email): ?Member;

    public function isEmailAddressTaken(EmailAddress $email, ?Uuid $except = null): bool;

    public function isDisplayNameTaken(LookupKey $displayName, ?Uuid $except = null): bool;

    /**
     * Members whose name or display name contains the term, on their lookup keys; erased members never match.
     *
     * @return list<Member> by display name
     */
    public function search(?LookupKey $term = null): array;
}
