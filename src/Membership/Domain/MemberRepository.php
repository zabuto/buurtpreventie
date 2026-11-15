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
     * Stores the member when the running transaction commits.
     *
     * @throws EmailAddressTaken when another member committed the same address in the meantime
     * @throws DisplayNameTaken when another member committed the same display name in the meantime
     */
    public function add(Member $member): void;

    public function isEmailAddressTaken(EmailAddress $email, ?Uuid $except = null): bool;

    public function isDisplayNameTaken(LookupKey $displayName, ?Uuid $except = null): bool;

    /**
     * Members whose name or display name contains the term, on their lookup keys; erased members never match.
     *
     * @return list<Member> by display name
     */
    public function search(?LookupKey $term = null): array;
}
