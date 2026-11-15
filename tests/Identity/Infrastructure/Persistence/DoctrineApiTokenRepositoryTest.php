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

    private function token(Uuid $memberId, #[SensitiveParameter] ?string $secret = null): ApiToken
    {
        return ApiToken::issue(
            $memberId,
            new TokenTerms('Script', [TokenScope::Read, TokenScope::Write]),
            $secret ?? 'bpt_' . \bin2hex(\random_bytes(32)),
            new DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        );
    }
}
