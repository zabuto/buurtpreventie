<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * How a member logs in. The e-mail address lives on the member, so the account holds only what proves who logs in.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'account_member', columns: ['member_id'])]
final class Account
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private readonly Uuid $id;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastLoginAt = null;

    private function __construct(
        #[ORM\Column(type: 'uuid')]
        private readonly Uuid $memberId,
    ) {
        $this->id = Uuid::v7();
    }

    public static function open(Uuid $memberId): self
    {
        return new self($memberId);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function memberId(): Uuid
    {
        return $this->memberId;
    }

    public function passwordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function recordLogin(DateTimeImmutable $now): void
    {
        $this->lastLoginAt = $now;
    }

    public function changePasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }
}
