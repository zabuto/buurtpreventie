<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * A request for a login code by mail. The code works only together with the id of this challenge, which stays in
 * the browser that asked for it, so a forwarded mail or someone who only sees the mail does not log in. Only a keyed
 * hash of id and code is stored: six digits are a million options, which a plain hash would give away in a second.
 * An address without a member gets a challenge too, so the answer never tells whether an address belongs to one.
 */
#[ORM\Entity]
#[ORM\Index(name: 'login_challenge_member', columns: ['member_id'])]
final class LoginChallenge
{
    public const int MAXIMUM_ATTEMPTS = 5;
    public const int VALIDITY_IN_MINUTES = 60;

    /** \z, not $: $ also matches before a trailing newline. */
    private const string ID_FORMAT = '/^[0-9a-f]{32}\z/';

    #[ORM\Column(type: 'utc_datetime_immutable')]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'smallint')]
    private int $attempts = 0;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $usedAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 32, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private readonly ?Uuid $memberId,
        #[ORM\Column(type: 'binary', length: 32, options: ['fixed' => true])]
        private readonly string $codeHash,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->expiresAt = $createdAt->modify(\sprintf('+%d minutes', self::VALIDITY_IN_MINUTES));
    }

    /**
     * @param string $id 128 random bits in lower-case hex, from the LoginCodeGenerator
     * @param Uuid|null $memberId null for an address without an active member
     * @param string $codeHash from LoginCodeHasher, over this id and the code
     *
     * @throws InvalidArgumentException for an id in another format
     */
    public static function open(string $id, ?Uuid $memberId, string $codeHash, DateTimeImmutable $now): self
    {
        if (1 !== \preg_match(self::ID_FORMAT, $id)) {
            throw new InvalidArgumentException('A login challenge id is 32 lower-case hex digits.');
        }

        return new self($id, $memberId, $codeHash, $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function memberId(): ?Uuid
    {
        return $this->memberId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function attemptsLeft(): int
    {
        return self::MAXIMUM_ATTEMPTS - $this->attempts;
    }

    /**
     * An attempt on an ended challenge does not count, so the attempts left stay as the member last saw them.
     */
    public function attempt(string $codeHash, DateTimeImmutable $now): CodeAttempt
    {
        if (!$this->isOpenAt($now)) {
            return CodeAttempt::Ended;
        }

        if (null === $this->memberId || !\hash_equals($this->codeHash, $codeHash)) {
            ++$this->attempts;

            return CodeAttempt::Wrong;
        }

        $this->usedAt = $now;

        return CodeAttempt::Accepted;
    }

    /**
     * A newer challenge of the same member replaces this one.
     */
    public function end(DateTimeImmutable $now): void
    {
        if ($now < $this->expiresAt) {
            $this->expiresAt = $now;
        }
    }

    public function isOpenAt(DateTimeImmutable $now): bool
    {
        return null === $this->usedAt && $now < $this->expiresAt && $this->attempts < self::MAXIMUM_ATTEMPTS;
    }
}
