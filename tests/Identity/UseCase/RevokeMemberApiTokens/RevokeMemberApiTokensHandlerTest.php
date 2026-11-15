<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\RevokeMemberApiTokens;

use App\Identity\UseCase\RevokeMemberApiTokens\RevokeMemberApiTokens;
use App\Identity\UseCase\RevokeMemberApiTokens\RevokeMemberApiTokensHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Tests\Identity\Fake\ApiTokenFixture;
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

final class RevokeMemberApiTokensHandlerTest extends TestCase
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
    public function it_revokes_every_token_of_the_member_and_no_other(): void
    {
        $first = ApiTokenFixture::stored($this->tokens, $this->memberId);
        $second = ApiTokenFixture::stored($this->tokens, $this->memberId);
        $other = ApiTokenFixture::stored($this->tokens, Uuid::v7());

        $this->handler()(new RevokeMemberApiTokens($this->memberId->toRfc4122()));

        $this->assertSame([false, false, true], [
            $first->isUsableAt(new DateTimeImmutable(self::NOW)),
            $second->isUsableAt(new DateTimeImmutable(self::NOW)),
            $other->isUsableAt(new DateTimeImmutable(self::NOW)),
        ]);
    }

    #[Test]
    public function it_records_each_token_it_revoked(): void
    {
        $active = ApiTokenFixture::stored($this->tokens, $this->memberId);
        ApiTokenFixture::stored($this->tokens, $this->memberId)->revoke(new DateTimeImmutable('2026-11-10T12:00:00+00:00'));

        $this->handler()(new RevokeMemberApiTokens($this->memberId->toRfc4122()));

        $this->assertEquals([new AuditedAction('api_token.revoked', 'api_token', $active->id())], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_revokes_nothing_when_access_is_denied(): void
    {
        $token = ApiTokenFixture::stored($this->tokens, $this->memberId);
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new RevokeMemberApiTokens($this->memberId->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['api_token.revoke_all', $this->memberId]], $authorization->asked);
        $this->assertNull($token->revokedAt());
    }

    private function handler(?FakeAuthorization $authorization = null): RevokeMemberApiTokensHandler
    {
        return new RevokeMemberApiTokensHandler(
            $this->tokens,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
        );
    }
}
