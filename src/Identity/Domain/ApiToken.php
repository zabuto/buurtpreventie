<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

/**
 * A personal key for scripts and MCP. Only the SHA-256 hash of its secret is stored: the secret is shown once, when
 * the token is created, and a copy of the database lets nobody in.
 */
#[ORM\Entity]
#[ORM\Index(name: 'api_token_member', columns: ['member_id'])]
final class ApiToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'binary', length: 32, unique: true, options: ['fixed' => true])]
    private string $secretHash;

    /** @var list<string> */
    #[ORM\Column(type: 'simple_array')]
    private array $scopes;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $expiresAt;

    private function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $memberId,
        TokenTerms $terms,
        #[SensitiveParameter]
        string $secret,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
        $this->secretHash = self::hash($secret);
        $this->name = $terms->name;
        $this->scopes = \array_map(static fn(TokenScope $scope): string => $scope->value, $terms->scopes);
        $this->expiresAt = $terms->expiresAt;
    }

    public static function issue(Uuid $memberId, TokenTerms $terms, #[SensitiveParameter] string $secret, DateTimeImmutable $now): self
    {
        return new self($memberId, $terms, $secret, $now);
    }

    /**
     * The hash a presented secret is looked up by.
     */
    public static function hash(#[SensitiveParameter] string $secret): string
    {
        return \hash('sha256', $secret, binary: true);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function memberId(): Uuid
    {
        return $this->memberId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function secretHash(): string
    {
        return $this->secretHash;
    }

    /**
     * @return list<TokenScope>
     */
    public function scopes(): array
    {
        return \array_map(TokenScope::from(...), $this->scopes);
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    /**
     * The token authenticated a request; it shows the member which tokens are still in use.
     */
    public function markUsed(DateTimeImmutable $now): void
    {
        $this->lastUsedAt = $now;
    }

    public function matches(#[SensitiveParameter] string $secret): bool
    {
        return \hash_equals($this->secretHash, self::hash($secret));
    }

    public function isUsableAt(DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && (null === $this->expiresAt || $now < $this->expiresAt);
    }

    /**
     * @return bool false when the token was revoked before
     */
    public function revoke(DateTimeImmutable $now): bool
    {
        if (null !== $this->revokedAt) {
            return false;
        }

        $this->revokedAt = $now;

        return true;
    }
}
