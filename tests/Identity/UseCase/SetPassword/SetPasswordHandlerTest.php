<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\SetPassword;

use App\Identity\UseCase\SetPassword\SetPassword;
use App\Identity\UseCase\SetPassword\SetPasswordHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Tests\Identity\Fake\InMemoryAccountRepository;
use App\Tests\Identity\Fake\PasswordHashers;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class SetPasswordHandlerTest extends TestCase
{
    private InMemoryAccountRepository $accounts;
    private RecordingAuditTrail $auditTrail;
    private Uuid $memberId;

    #[Override]
    protected function setUp(): void
    {
        $this->accounts = new InMemoryAccountRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->memberId = Uuid::v7();
    }

    #[Test]
    public function it_opens_an_account_with_the_hashed_password(): void
    {
        $this->handler()(new SetPassword($this->memberId->toRfc4122(), 'een lang voorbeeldwachtwoord'));

        $hash = (string) $this->accounts->ofMember($this->memberId)?->passwordHash();
        $this->assertTrue(PasswordHashers::cheap()->verify($hash, 'een lang voorbeeldwachtwoord'));
        $this->assertStringNotContainsString('voorbeeldwachtwoord', $hash);
    }

    #[Test]
    public function it_replaces_the_password_of_an_existing_account(): void
    {
        $handler = $this->handler();
        $handler(new SetPassword($this->memberId->toRfc4122(), 'een lang voorbeeldwachtwoord'));

        $handler(new SetPassword($this->memberId->toRfc4122(), 'een ander lang wachtwoord'));

        $hash = (string) $this->accounts->ofMember($this->memberId)?->passwordHash();
        $this->assertSame([false, true], [
            PasswordHashers::cheap()->verify($hash, 'een lang voorbeeldwachtwoord'),
            PasswordHashers::cheap()->verify($hash, 'een ander lang wachtwoord'),
        ]);
    }

    /**
     * The moment is signed into every "stay logged in" cookie, so the new password ends them all.
     */
    #[Test]
    public function it_notes_when_the_password_changed(): void
    {
        $this->handler()(new SetPassword($this->memberId->toRfc4122(), 'een lang voorbeeldwachtwoord'));

        $this->assertEquals(new DateTimeImmutable('2026-11-16T10:00:00+00:00'), $this->accounts->ofMember($this->memberId)?->credentialsChangedAt());
    }

    #[Test]
    public function it_records_the_change_without_the_password(): void
    {
        $this->handler()(new SetPassword($this->memberId->toRfc4122(), 'een lang voorbeeldwachtwoord'));

        $this->assertEquals([new AuditedAction('account.password_changed', 'member', $this->memberId)], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_sets_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new SetPassword($this->memberId->toRfc4122(), 'een lang voorbeeldwachtwoord'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['account.set_password', $this->memberId]], $authorization->asked);
        $this->assertSame([null, []], [$this->accounts->ofMember($this->memberId), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): SetPasswordHandler
    {
        return new SetPasswordHandler(
            $this->accounts,
            PasswordHashers::cheap(),
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock('2026-11-16T10:00:00+00:00'),
        );
    }
}
