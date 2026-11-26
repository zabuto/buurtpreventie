<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Domain\ApiToken;
use App\Identity\Infrastructure\Security\TokenLimitTrace;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Notification;
use App\Tests\Identity\Fake\ApiTokenFixture;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Shared\Fake\RecordingNotifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * A stolen token shows up the first time on a day that it reaches the daily limit: once in the audit log and once in the
 * mailbox of its member. Every later refusal that day leaves no trace, so a client in a loop sends no flood of mails.
 */
final class TokenLimitTraceTest extends TestCase
{
    private InMemoryApiTokenRepository $tokens;

    private RecordingAuditTrail $auditTrail;

    private RecordingNotifier $notifier;

    private MockClock $clock;

    private TokenLimitTrace $trace;

    protected function setUp(): void
    {
        $this->tokens = new InMemoryApiTokenRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->notifier = new RecordingNotifier();
        $this->clock = new MockClock('2026-11-16T10:00:00+00:00');
        $this->trace = new TokenLimitTrace($this->tokens, $this->auditTrail, $this->notifier, new ImmediateTransaction(), $this->clock, new ArrayAdapter());
    }

    #[Test]
    public function it_records_and_mails_the_first_time_a_token_reaches_the_daily_limit(): void
    {
        $token = $this->token();

        $this->trace->dailyLimitReached($token->id()->toRfc4122());

        self::assertEquals(
            [new AuditedAction('api_token.limit_reached', 'api_token', $token->id(), ['name' => ['old' => null, 'new' => 'Script']])],
            $this->auditTrail->recorded,
        );
        self::assertEquals(
            [new Notification($token->memberId(), 'account.token_limit_reached', ['tokenName' => 'Script', 'reachedAt' => '2026-11-16T10:00:00+00:00'])],
            $this->notifier->sent,
        );
    }

    #[Test]
    public function it_leaves_no_trace_of_a_later_refusal_on_the_same_local_day(): void
    {
        $token = $this->token();
        $this->trace->dailyLimitReached($token->id()->toRfc4122());
        $this->clock->modify('2026-11-16T22:59:59+00:00');

        $this->trace->dailyLimitReached($token->id()->toRfc4122());

        self::assertSame([1, 1], [\count($this->auditTrail->recorded), \count($this->notifier->sent)]);
    }

    /**
     * 23:00 UTC in November is midnight on the local clock (Europe/Amsterdam).
     */
    #[Test]
    public function it_traces_the_token_again_on_the_next_local_day(): void
    {
        $token = $this->token();
        $this->trace->dailyLimitReached($token->id()->toRfc4122());
        $this->clock->modify('2026-11-16T23:00:00+00:00');

        $this->trace->dailyLimitReached($token->id()->toRfc4122());

        self::assertSame([2, 2], [\count($this->auditTrail->recorded), \count($this->notifier->sent)]);
    }

    #[Test]
    public function it_traces_each_token_apart_on_the_same_day(): void
    {
        $this->trace->dailyLimitReached($this->token()->id()->toRfc4122());

        $this->trace->dailyLimitReached($this->token()->id()->toRfc4122());

        self::assertSame([2, 2], [\count($this->auditTrail->recorded), \count($this->notifier->sent)]);
    }

    private function token(): ApiToken
    {
        return ApiTokenFixture::stored($this->tokens, Uuid::v7());
    }
}
