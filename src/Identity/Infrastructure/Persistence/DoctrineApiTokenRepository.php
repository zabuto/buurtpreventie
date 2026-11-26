<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\ApiTokenRepository;
use App\Shared\Domain\NotFound;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineApiTokenRepository implements ApiTokenRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(Uuid $id): ApiToken
    {
        return $this->entityManager->find(ApiToken::class, $id) ?? throw NotFound::forId('API token', $id);
    }

    #[Override]
    public function add(#[SensitiveParameter] ApiToken $token): void
    {
        $this->entityManager->persist($token);
    }

    #[Override]
    public function ofMember(Uuid $memberId): array
    {
        /** @var list<ApiToken> */
        return $this->entityManager->getRepository(ApiToken::class)->findBy(['memberId' => $memberId]);
    }

    #[Override]
    public function usableAt(DateTimeImmutable $now): array
    {
        /** @var list<ApiToken> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('token')
            ->from(ApiToken::class, 'token')
            ->where('token.revokedAt IS NULL')
            ->andWhere('token.expiresAt > :now')
            ->setParameter('now', $now, 'utc_datetime_immutable')
            ->getQuery()
            ->getResult()
        ;
    }

    #[Override]
    public function bySecretHash(#[SensitiveParameter] string $secretHash): ?ApiToken
    {
        return $this->entityManager->getRepository(ApiToken::class)->findOneBy(['secretHash' => $secretHash]);
    }
}
