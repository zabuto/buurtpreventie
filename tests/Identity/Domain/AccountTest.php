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
}
