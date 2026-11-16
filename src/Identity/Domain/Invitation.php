<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

/**
 * A link that logs a new member in once, for 48 hours: a new member has no screen yet to ask for a code. Only the
 * SHA-256 hash of its secret is stored, as for an API token.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'invitation_secret_hash', columns: ['secret_hash'])]
#[ORM\Index(name: 'invitation_member', columns: ['member_id'])]
final class Invitation
{
    public const int VALIDITY_IN_HOURS = 48;

    /** \z, not $: $ also matches before a trailing newline. */
    private const string SECRET_FORMAT = '/^bpi_[0-9a-f]{64}\z/';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private readonly Uuid $id;

    #[ORM\Column(type: 'binary', length: 32, options: ['fixed' => true])]
    private readonly string $secretHash;

    #[ORM\Column(type: 'utc_datetime_immutable')]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $usedAt = null;

    private function __construct(
        #[ORM\Column(type: 'uuid')]
        private readonly Uuid $memberId,
        #[SensitiveParameter]
        string $secret,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
        $this->secretHash = self::hash($secret);
        $this->expiresAt = $createdAt->modify(\sprintf('+%d hours', self::VALIDITY_IN_HOURS));
    }

    /**
     * @throws InvalidArgumentException for a secret that is not bpi_ followed by 64 lower-case hex digits
     */
    public static function issue(Uuid $memberId, #[SensitiveParameter] string $secret, DateTimeImmutable $now): self
    {
        if (1 !== \preg_match(self::SECRET_FORMAT, $secret)) {
            throw new InvalidArgumentException('An invitation secret is bpi_ followed by 64 lower-case hex digits.');
        }

        return new self($memberId, $secret, $now);
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

    public function secretHash(): string
    {
        return $this->secretHash;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * @return bool false when the invitation was used, expired or replaced
     */
    public function accept(DateTimeImmutable $now): bool
    {
        if (!$this->isOpenAt($now)) {
            return false;
        }

        $this->usedAt = $now;

        return true;
    }

    /**
     * A newer invitation of the same member replaces this one.
     */
    public function end(DateTimeImmutable $now): void
    {
        if ($now < $this->expiresAt) {
            $this->expiresAt = $now;
        }
    }

    public function isOpenAt(DateTimeImmutable $now): bool
    {
        return null === $this->usedAt && $now < $this->expiresAt;
    }
}
