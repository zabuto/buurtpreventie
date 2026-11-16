<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

/**
 * A personal key for scripts and MCP. Only the SHA-256 hash of its secret is stored: the secret is shown once, when
 * the token is created, and a copy of the database lets nobody in.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'api_token_secret_hash', columns: ['secret_hash'])]
#[ORM\Index(name: 'api_token_member', columns: ['member_id'])]
final class ApiToken
{
    /** \z, not $: $ also matches before a trailing newline. */
    private const string SECRET_FORMAT = '/^bpt_[0-9a-f]{64}\z/';

    private const int USE_RECORDING_INTERVAL = 60;

    public const int MAXIMUM_LIFETIME_IN_DAYS = 365;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private readonly Uuid $id;

    #[ORM\Column(type: 'binary', length: 32, options: ['fixed' => true])]
    private readonly string $secretHash;

    /** @var list<string> */
    #[ORM\Column(type: 'simple_array')]
    private readonly array $scopes;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    #[ORM\Column(length: 100)]
    private readonly string $name;

    #[ORM\Column(type: 'utc_datetime_immutable')]
    private readonly DateTimeImmutable $expiresAt;

    private function __construct(
        #[ORM\Column(type: 'uuid')]
        private readonly Uuid $memberId,
        TokenTerms $terms,
        #[SensitiveParameter]
        string $secret,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
        $this->secretHash = self::hash($secret);
        $this->name = $terms->name;
        $this->scopes = \array_map(static fn(TokenScope $scope): string => $scope->value, $terms->scopes);
        $this->expiresAt = $terms->expiresAt;
    }

    /**
     * The secret comes from the SecretGenerator, never from a person: a typed or truncated secret is guessable.
     *
     * @throws InvalidArgumentException for a secret that is not bpt_ followed by 64 lower-case hex digits
     * @throws InvalidTokenExpiry for an expiry not after $now or more than 365 days after it
     */
    public static function issue(Uuid $memberId, TokenTerms $terms, #[SensitiveParameter] string $secret, DateTimeImmutable $now): self
    {
        if (1 !== \preg_match(self::SECRET_FORMAT, $secret)) {
            throw new InvalidArgumentException('An API token secret is bpt_ followed by 64 lower-case hex digits.');
        }
        if ($terms->expiresAt <= $now || $terms->expiresAt > $now->modify(\sprintf('+%d days', self::MAXIMUM_LIFETIME_IN_DAYS))) {
            throw new InvalidTokenExpiry(\sprintf('A token expires within %d days after it is issued.', self::MAXIMUM_LIFETIME_IN_DAYS));
        }

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

    public function expiresAt(): DateTimeImmutable
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
     * The token authenticated a request, which shows the member which tokens are in use. A use within a minute of the
     * last one is not recorded, so a script does not turn every request into a write.
     *
     * @return bool whether the moment was recorded
     */
    public function markUsed(DateTimeImmutable $now): bool
    {
        if (null !== $this->lastUsedAt && ($now->getTimestamp() - $this->lastUsedAt->getTimestamp()) < self::USE_RECORDING_INTERVAL) {
            return false;
        }

        $this->lastUsedAt = $now;

        return true;
    }

    /**
     * Why the token lets nobody in at this moment, or null when it admits its member. A revoked token reports that
     * before anything else, so the log shows the action that ended it.
     */
    public function refusalAt(DateTimeImmutable $now, bool $memberIsActive): ?TokenRefusal
    {
        return match (true) {
            null !== $this->revokedAt => TokenRefusal::Revoked,
            $now >= $this->expiresAt => TokenRefusal::Expired,
            !$memberIsActive => TokenRefusal::MemberInactive,
            default => null,
        };
    }

    public function matches(#[SensitiveParameter] string $secret): bool
    {
        return \hash_equals($this->secretHash, self::hash($secret));
    }

    public function isUsableAt(DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && $now < $this->expiresAt;
    }

    /**
     * @return bool false when the token was already revoked
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
