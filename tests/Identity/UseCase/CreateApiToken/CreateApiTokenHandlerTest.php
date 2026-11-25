<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\CreateApiToken;

use App\Identity\Domain\ApiTokensDisabled;
use App\Identity\Domain\InvalidTokenExpiry;
use App\Identity\Domain\TokenScope;
use App\Identity\UseCase\CreateApiToken\CreateApiToken;
use App\Identity\UseCase\CreateApiToken\CreateApiTokenHandler;
use App\Settings\Domain\Setting;
use App\Settings\UseCase\GetApiTokenAccess\GetApiTokenAccessHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Notification;
use App\Shared\Domain\ReauthenticationRequired;
use App\Tests\Identity\Fake\FixedSecretGenerator;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use App\Tests\Shared\Fake\FixedRecentAuthentication;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Shared\Fake\RecordingNotifier;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class CreateApiTokenHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';
    private const string IN_90_DAYS = '2027-02-13T12:00:00+00:00';

    private InMemoryApiTokenRepository $tokens;
    private RecordingAuditTrail $auditTrail;
    private RecordingNotifier $notifier;
    private InMemorySettingRepository $settings;
    private Uuid $memberId;

    #[Override]
    protected function setUp(): void
    {
        $this->tokens = new InMemoryApiTokenRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->notifier = new RecordingNotifier();
        $this->settings = new InMemorySettingRepository();
        $this->memberId = Uuid::v7();
    }

    #[Test]
    public function it_hands_out_the_secret_once_and_stores_its_hash(): void
    {
        $output = $this->handler()(new CreateApiToken('Script', ['read', 'write'], self::IN_90_DAYS, $this->memberId->toRfc4122()));

        $token = $this->tokens->get(Uuid::fromString($output->id));
        $this->assertSame('bpt_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', $output->token);
        $this->assertSame(
            [true, 'Script', [TokenScope::Read, TokenScope::Write]],
            [$token->matches('bpt_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'), $token->name(), $token->scopes()],
        );
        $this->assertEquals(new DateTimeImmutable(self::IN_90_DAYS), $token->expiresAt());
    }

    #[Test]
    public function it_creates_the_token_for_the_member_acting_without_a_member_id(): void
    {
        $output = $this->handler()(new CreateApiToken('Script', ['read'], self::IN_90_DAYS));

        $this->assertTrue($this->tokens->get(Uuid::fromString($output->id))->memberId()->equals($this->memberId));
    }

    #[Test]
    public function it_records_the_new_token_without_its_secret(): void
    {
        $output = $this->handler()(new CreateApiToken('Script', ['read'], self::IN_90_DAYS));

        $this->assertEquals(
            [new AuditedAction('api_token.created', 'api_token', Uuid::fromString($output->id), [
                'name' => ['old' => null, 'new' => 'Script'],
                'scopes' => ['old' => null, 'new' => 'read'],
                'expiresAt' => ['old' => null, 'new' => self::IN_90_DAYS],
            ])],
            $this->auditTrail->recorded,
        );
    }

    /**
     * A token made by someone else on the member's name, or with a stolen session, then reaches the member's own
     * mailbox. The secret stays out: a mail is no safe place for it.
     */
    #[Test]
    public function it_tells_the_member_about_the_new_token_without_its_secret(): void
    {
        $this->handler()(new CreateApiToken('Script', ['read', 'write'], self::IN_90_DAYS));

        $this->assertEquals(
            [new Notification($this->memberId, 'account.token_created', [
                'tokenName' => 'Script',
                'scopes' => 'read,write',
                'expiresAt' => self::IN_90_DAYS,
            ])],
            $this->notifier->sent,
        );
    }

    #[Test]
    public function it_creates_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new CreateApiToken('Script', ['read'], self::IN_90_DAYS));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['api_token.create', $this->memberId]], $authorization->asked);
        $this->assertSame([[], [], []], [$this->tokens->ofMember($this->memberId), $this->auditTrail->recorded, $this->notifier->sent]);
    }

    /**
     * A stolen session that stays logged in, or a leaked API token, would otherwise mint a token of its own that
     * outlives the session.
     */
    #[Test]
    public function it_creates_nothing_without_a_recent_login(): void
    {
        $refusal = null;
        try {
            $this->handler(recent: false)(new CreateApiToken('Script', ['read'], self::IN_90_DAYS));
        } catch (ReauthenticationRequired $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(ReauthenticationRequired::class, $refusal);
        $this->assertSame([[], []], [$this->tokens->ofMember($this->memberId), $this->auditTrail->recorded]);
    }

    /**
     * Switching tokens off is a step after a leak, so no new token may slip in until the admin switches them on.
     */
    #[Test]
    public function it_creates_nothing_while_api_tokens_are_switched_off(): void
    {
        $this->settings->add(Setting::of('integrations.api_access', '0'));

        $refusal = null;
        try {
            $this->handler()(new CreateApiToken('Script', ['read'], self::IN_90_DAYS));
        } catch (ApiTokensDisabled $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(ApiTokensDisabled::class, $refusal);
        $this->assertSame([[], []], [$this->tokens->ofMember($this->memberId), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_refuses_an_expiry_more_than_365_days_ahead(): void
    {
        $this->expectException(InvalidTokenExpiry::class);

        $this->handler()(new CreateApiToken('Script', ['read'], '2027-11-15T12:00:01+00:00'));
    }

    #[Test]
    public function it_refuses_when_nobody_acts_and_no_member_is_given(): void
    {
        $this->expectException(AccessDenied::class);

        $this->handler(withActor: false)(new CreateApiToken('Script', ['read'], self::IN_90_DAYS));
    }

    private function handler(?FakeAuthorization $authorization = null, bool $recent = true, bool $withActor = true): CreateApiTokenHandler
    {
        return new CreateApiTokenHandler(
            $this->tokens,
            new FixedSecretGenerator(),
            $authorization ?? FakeAuthorization::granting(),
            new FixedExecutionContext($withActor ? new Actor($this->memberId, []) : null),
            new FixedRecentAuthentication($recent),
            $this->auditTrail,
            $this->notifier,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
            new GetApiTokenAccessHandler($this->settings),
        );
    }
}
