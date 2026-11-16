<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\Account;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AccountTest extends TestCase
{
    #[Test]
    public function it_opens_without_a_password_or_login(): void
    {
        $account = Account::open(Uuid::v7());

        $this->assertSame([null, null], [$account->passwordHash(), $account->lastLoginAt()]);
    }

    #[Test]
    public function it_remembers_the_last_login(): void
    {
        $account = Account::open(Uuid::v7());

        $account->recordLogin(new DateTimeImmutable('2026-11-16T08:00:00+00:00'));

        $this->assertEquals(new DateTimeImmutable('2026-11-16T08:00:00+00:00'), $account->lastLoginAt());
    }

    #[Test]
    public function it_records_when_a_new_password_was_set(): void
    {
        $account = Account::open(Uuid::v7());

        $account->changePassword('$2y$04$new', new DateTimeImmutable('2026-11-16T09:00:00+00:00'));

        $this->assertEquals(
            ['$2y$04$new', new DateTimeImmutable('2026-11-16T09:00:00+00:00')],
            [$account->passwordHash(), $account->credentialsChangedAt()],
        );
    }

    /**
     * A renewed hash holds the same password, so devices that stay logged in keep working.
     */
    #[Test]
    public function it_keeps_the_moment_of_the_last_new_password_when_the_hash_is_renewed(): void
    {
        $account = Account::open(Uuid::v7());
        $account->changePassword('$2y$04$old', new DateTimeImmutable('2026-11-16T09:00:00+00:00'));

        $account->renewPasswordHash('$argon2id$renewed');

        $this->assertEquals(
            ['$argon2id$renewed', new DateTimeImmutable('2026-11-16T09:00:00+00:00')],
            [$account->passwordHash(), $account->credentialsChangedAt()],
        );
    }
}
