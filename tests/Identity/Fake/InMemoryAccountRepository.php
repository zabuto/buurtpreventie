<?php

declare(strict_types=1);

namespace App\Tests\Identity\Fake;

use App\Identity\Domain\Account;
use App\Identity\Domain\AccountRepository;
use Override;
use Symfony\Component\Uid\Uuid;

final class InMemoryAccountRepository implements AccountRepository
{
    /** @var array<string, Account> */
    private array $accounts = [];

    #[Override]
    public function ofMember(Uuid $memberId): ?Account
    {
        return $this->accounts[$memberId->toRfc4122()] ?? null;
    }

    #[Override]
    public function add(Account $account): void
    {
        $this->accounts[$account->memberId()->toRfc4122()] = $account;
    }
}
