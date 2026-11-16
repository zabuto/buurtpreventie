<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\Invitation;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Identity\Infrastructure\RandomSecretGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RandomSecretGeneratorTest extends TestCase
{
    #[Test]
    public function it_makes_a_secret_that_an_api_token_accepts(): void
    {
        $secret = new RandomSecretGenerator()->newSecret();

        $token = ApiToken::issue(Uuid::v7(), new TokenTerms('Script', [TokenScope::Read]), $secret, new DateTimeImmutable('2026-11-15T12:00:00+00:00'));

        $this->assertTrue($token->matches($secret));
    }

    #[Test]
    public function it_never_repeats_a_secret(): void
    {
        $generator = new RandomSecretGenerator();

        $this->assertNotSame($generator->newSecret(), $generator->newSecret());
    }

    #[Test]
    public function it_makes_an_invitation_secret_that_an_invitation_accepts(): void
    {
        $secret = new RandomSecretGenerator()->newInvitationSecret();

        $invitation = Invitation::issue(Uuid::v7(), $secret, new DateTimeImmutable('2026-11-15T12:00:00+00:00'));

        $this->assertSame(Invitation::hash($secret), $invitation->secretHash());
    }

    #[Test]
    public function it_never_repeats_an_invitation_secret(): void
    {
        $generator = new RandomSecretGenerator();

        $this->assertNotSame($generator->newInvitationSecret(), $generator->newInvitationSecret());
    }
}
