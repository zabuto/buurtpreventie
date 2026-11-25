<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Persistence;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Identity\Infrastructure\Persistence\DoctrineApiTokenRepository;
use App\Shared\Domain\NotFound;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[Group('database')]
final class DoctrineApiTokenRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineApiTokenRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineApiTokenRepository($entityManager);
    }

    #[Test]
    public function it_brings_a_token_back_that_matches_its_secret(): void
    {
        $secret = 'bpt_' . \str_repeat('0a', 32);
        $token = $this->token(Uuid::v7(), $secret);
        $this->repository->add($token);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->repository->get($token->id());

        $this->assertSame([true, [TokenScope::Read, TokenScope::Write]], [$read->matches($secret), $read->scopes()]);
    }

    #[Test]
    public function it_reports_an_unknown_token(): void
    {
        $this->expectException(NotFound::class);

        $this->repository->get(Uuid::v7());
    }

    #[Test]
    public function it_finds_every_token_of_the_member_revoked_ones_included(): void
    {
        $memberId = Uuid::v7();
        $active = $this->token($memberId);
        $revoked = $this->token($memberId);
        $revoked->revoke(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));
        $ofSomeoneElse = $this->token(Uuid::v7());
        foreach ([$active, $revoked, $ofSomeoneElse] as $token) {
            $this->repository->add($token);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->repository->ofMember($memberId);

        $this->assertEqualsCanonicalizing(
            [$active->id()->toRfc4122(), $revoked->id()->toRfc4122()],
            \array_map(static fn(#[SensitiveParameter] ApiToken $token): string => $token->id()->toRfc4122(), $found),
        );
    }

    /**
     * A token expires at its expiry moment, as the authenticator refuses it from then on. The moment is local time, as
     * the clock of the server may give it, while the database holds UTC.
     */
    #[Test]
    public function it_finds_the_tokens_of_every_member_that_still_let_someone_in(): void
    {
        $now = new DateTimeImmutable('2026-11-16T11:00:00+01:00');
        $active = $this->token(Uuid::v7());
        $ofAnother = $this->token(Uuid::v7());
        $revoked = $this->token(Uuid::v7());
        $revoked->revoke(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));
        $expiresNow = $this->token(Uuid::v7(), expiresAt: $now);
        $expiresJustAfter = $this->token(Uuid::v7(), expiresAt: $now->modify('+1 second'));
        foreach ([$active, $ofAnother, $revoked, $expiresNow, $expiresJustAfter] as $token) {
            $this->repository->add($token);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = \array_map(static fn(#[SensitiveParameter] ApiToken $token): string => $token->id()->toRfc4122(), $this->repository->usableAt($now));

        $ours = [$active, $ofAnother, $revoked, $expiresNow, $expiresJustAfter];
        $this->assertEqualsCanonicalizing(
            [$active->id()->toRfc4122(), $ofAnother->id()->toRfc4122(), $expiresJustAfter->id()->toRfc4122()],
            \array_values(\array_intersect($found, \array_map(static fn(#[SensitiveParameter] ApiToken $token): string => $token->id()->toRfc4122(), $ours))),
        );
    }

    private function token(Uuid $memberId, #[SensitiveParameter] ?string $secret = null, ?DateTimeImmutable $expiresAt = null): ApiToken
    {
        return ApiToken::issue(
            $memberId,
            new TokenTerms('Script', [TokenScope::Read, TokenScope::Write], $expiresAt ?? new DateTimeImmutable('2027-11-01T00:00:00+00:00')),
            $secret ?? 'bpt_' . \bin2hex(\random_bytes(32)),
            new DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        );
    }
}
