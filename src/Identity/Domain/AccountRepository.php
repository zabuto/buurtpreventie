<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use Symfony\Component\Uid\Uuid;

interface AccountRepository
{
    public function ofMember(Uuid $memberId): ?Account;

    /**
     * Stores the account when the running transaction commits.
     */
    public function add(Account $account): void;
}
