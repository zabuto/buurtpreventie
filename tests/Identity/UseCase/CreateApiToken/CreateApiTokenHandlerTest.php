<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\CreateApiToken;

use App\Identity\Domain\TokenScope;
use App\Identity\UseCase\CreateApiToken\CreateApiToken;
use App\Identity\UseCase\CreateApiToken\CreateApiTokenHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Tests\Identity\Fake\FixedSecretGenerator;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class CreateApiTokenHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    private InMemoryApiTokenRepository $tokens;
    private RecordingAuditTrail $auditTrail;
    private Uuid $memberId;

    #[Override]
    protected function setUp(): void
    {
        $this->tokens = new InMemoryApiTokenRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->memberId = Uuid::v7();
    }

    #[Test]
    public function it_hands_out_the_secret_once_and_stores_its_hash(): void
    {
        $output = $this->handler()(new CreateApiToken($this->memberId->toRfc4122(), 'Script', ['read', 'write'], '2027-11-15T12:00:00+00:00'));

        $token = $this->tokens->get(Uuid::fromString($output->id));
        $this->assertSame('bpt_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', $output->token);
        $this->assertSame(
            [true, 'Script', [TokenScope::Read, TokenScope::Write]],
            [$token->matches('bpt_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'), $token->name(), $token->scopes()],
        );
        $this->assertEquals(new DateTimeImmutable('2027-11-15T12:00:00+00:00'), $token->expiresAt());
    }

    #[Test]
    public function it_records_the_new_token_without_its_secret(): void
    {
        $output = $this->handler()(new CreateApiToken($this->memberId->toRfc4122(), 'Script', ['read']));

        $this->assertEquals(
            [new AuditedAction('api_token.created', 'api_token', Uuid::fromString($output->id), [
                'name' => ['old' => null, 'new' => 'Script'],
                'scopes' => ['old' => null, 'new' => 'read'],
            ])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_creates_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new CreateApiToken($this->memberId->toRfc4122(), 'Script', ['read']));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['api_token.create', $this->memberId]], $authorization->asked);
        $this->assertSame([[], []], [$this->tokens->ofMember($this->memberId), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): CreateApiTokenHandler
    {
        return new CreateApiTokenHandler(
            $this->tokens,
            new FixedSecretGenerator(),
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
        );
    }
}
