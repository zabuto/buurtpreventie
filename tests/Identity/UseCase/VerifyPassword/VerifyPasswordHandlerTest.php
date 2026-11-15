<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\VerifyPassword;

use App\Identity\Domain\Account;
use App\Identity\UseCase\VerifyPassword\VerifyPassword;
use App\Identity\UseCase\VerifyPassword\VerifyPasswordHandler;
use App\Tests\Identity\Fake\InMemoryAccountRepository;
use App\Tests\Identity\Fake\PasswordHashers;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class VerifyPasswordHandlerTest extends TestCase
{
    private InMemoryAccountRepository $accounts;
    private Uuid $memberId;

    #[Override]
    protected function setUp(): void
    {
        $this->accounts = new InMemoryAccountRepository();
        $this->memberId = Uuid::v7();
    }

    #[Test]
    public function it_accepts_the_right_password(): void
    {
        $this->accountWithHash(PasswordHashers::cheap()->hash('een lang voorbeeldwachtwoord'));

        $output = $this->handler()(new VerifyPassword($this->memberId->toRfc4122(), 'een lang voorbeeldwachtwoord'));

        $this->assertTrue($output->valid);
    }

    #[Test]
    public function it_refuses_another_password(): void
    {
        $this->accountWithHash(PasswordHashers::cheap()->hash('een lang voorbeeldwachtwoord'));

        $output = $this->handler()(new VerifyPassword($this->memberId->toRfc4122(), 'een ander wachtwoord'));

        $this->assertFalse($output->valid);
    }

    /**
     * The legacy app hashed passwords with bcrypt through the same "auto" hasher, so members keep their password.
     */
    #[Test]
    public function it_accepts_a_bcrypt_hash_as_the_legacy_app_made_it(): void
    {
        $this->accountWithHash('$2y$04$yqT6O03mkO0R6pdy1YQDF./fvud.hxzYZLg7Yl0gGg1x3HwLXL15q');

        $output = $this->handler()(new VerifyPassword($this->memberId->toRfc4122(), 'legacy-voorbeeldwachtwoord'));

        $this->assertTrue($output->valid);
    }

    #[Test]
    public function it_refuses_a_member_without_a_password(): void
    {
        $this->accounts->add(Account::open($this->memberId));

        $output = $this->handler()(new VerifyPassword($this->memberId->toRfc4122(), 'een lang voorbeeldwachtwoord'));

        $this->assertFalse($output->valid);
    }

    #[Test]
    public function it_refuses_a_member_without_an_account(): void
    {
        $output = $this->handler()(new VerifyPassword($this->memberId->toRfc4122(), 'een lang voorbeeldwachtwoord'));

        $this->assertFalse($output->valid);
    }

    private function accountWithHash(string $hash): void
    {
        $account = Account::open($this->memberId);
        $account->changePasswordHash($hash);
        $this->accounts->add($account);
    }

    private function handler(): VerifyPasswordHandler
    {
        return new VerifyPasswordHandler($this->accounts, PasswordHashers::cheap());
    }
}
