<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\RevokeApiToken;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Identity\UseCase\RevokeApiToken\RevokeApiToken;
use App\Identity\UseCase\RevokeApiToken\RevokeApiTokenHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
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

final class RevokeApiTokenHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    private InMemoryApiTokenRepository $tokens;
    private RecordingAuditTrail $auditTrail;
    private ApiToken $token;

    #[Override]
    protected function setUp(): void
    {
        $this->tokens = new InMemoryApiTokenRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->token = ApiToken::issue(
            Uuid::v7(),
            new TokenTerms('Script', [TokenScope::Read]),
            'bpt_vast-geheim',
            new DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        );
        $this->tokens->add($this->token);
    }

    #[Test]
    public function it_revokes_the_token_at_the_current_time(): void
    {
        $this->handler()(new RevokeApiToken($this->token->id()->toRfc4122()));

        $this->assertEquals(new DateTimeImmutable(self::NOW), $this->token->revokedAt());
    }

    #[Test]
    public function it_records_the_revocation(): void
    {
        $this->handler()(new RevokeApiToken($this->token->id()->toRfc4122()));

        $this->assertEquals([new AuditedAction('api_token.revoked', 'api_token', $this->token->id())], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_records_nothing_for_a_token_that_was_revoked_before(): void
    {
        $this->token->revoke(new DateTimeImmutable('2026-11-10T12:00:00+00:00'));

        $this->handler()(new RevokeApiToken($this->token->id()->toRfc4122()));

        $this->assertSame([], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_revokes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new RevokeApiToken($this->token->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['api_token.revoke', $this->token]], $authorization->asked);
        $this->assertNull($this->token->revokedAt());
    }

    private function handler(?FakeAuthorization $authorization = null): RevokeApiTokenHandler
    {
        return new RevokeApiTokenHandler(
            $this->tokens,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
        );
    }
}
