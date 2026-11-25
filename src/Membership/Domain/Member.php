<?php

declare(strict_types=1);

namespace App\Membership\Domain;

use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\PhoneNumber;
use App\Shared\Domain\Role;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A member's profile. The e-mail address and the display name are unique; the full name is not, so two members may
 * share it and tell each other apart by display name.
 *
 * Erasing keeps the member, so every id other modules hold keeps pointing at one; it wipes everything that
 * identifies the person.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'member_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'member_display_name_key', columns: ['display_name_key'])]
#[ORM\Index(name: 'member_name_key', columns: ['name_key'])]
final class Member
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private readonly Uuid $id;

    /** Binary, as an address with an accent differs from the one without; EmailAddress lowercases it already. */
    #[ORM\Column(length: 180, nullable: true, options: ['collation' => 'utf8mb4_bin'])]
    private ?string $email = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true, options: ['collation' => 'utf8mb4_bin'])]
    private ?string $nameKey = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $displayName = null;

    /** Unique; NULL for an erased member, which InnoDB allows more than once. */
    #[ORM\Column(length: 100, nullable: true, options: ['collation' => 'utf8mb4_bin'])]
    private ?string $displayNameKey = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    /** @var list<string> */
    #[ORM\Column(type: 'simple_array', nullable: true)]
    private array $roles = [];

    #[ORM\Column]
    private bool $consentContactShare = false;

    #[ORM\Column]
    private bool $certified = false;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private bool $notifyOpenRounds = false;

    /**
     * The admin lets this member use the MCP server with every role, whichever roles the association allows there.
     */
    #[ORM\Column]
    private bool $mcpAllowed = false;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $erasedAt = null;

    private function __construct()
    {
        $this->id = Uuid::v7();
    }

    public static function register(MemberDetails $details): self
    {
        $member = new self();
        $member->change($details);

        return $member;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function email(): ?EmailAddress
    {
        return null === $this->email ? null : EmailAddress::fromString($this->email);
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function nameKey(): ?string
    {
        return $this->nameKey;
    }

    public function displayName(): ?string
    {
        return $this->displayName;
    }

    public function displayNameKey(): ?string
    {
        return $this->displayNameKey;
    }

    public function phone(): ?PhoneNumber
    {
        return null === $this->phone ? null : PhoneNumber::fromString($this->phone);
    }

    public function address(): ?string
    {
        return $this->address;
    }

    /**
     * @return list<Role>
     */
    public function roles(): array
    {
        return \array_map(Role::from(...), $this->roles);
    }

    public function hasRole(Role $role): bool
    {
        return \in_array($role->value, $this->roles, strict: true);
    }

    public function consentsToContactShare(): bool
    {
        return $this->consentContactShare;
    }

    public function isCertified(): bool
    {
        return $this->certified;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function wantsOpenRoundNotifications(): bool
    {
        return $this->notifyOpenRounds;
    }

    public function isMcpAllowed(): bool
    {
        return $this->mcpAllowed;
    }

    public function isErased(): bool
    {
        return null !== $this->erasedAt;
    }

    public function erasedAt(): ?DateTimeImmutable
    {
        return $this->erasedAt;
    }

    /**
     * @throws MemberErased
     */
    public function change(MemberDetails $details): void
    {
        $this->refuseWhenErased();

        $this->email = $details->email->value;
        $this->name = $details->name;
        $this->nameKey = LookupKey::fromName($details->name)->value;
        $this->displayName = $details->displayName;
        $this->displayNameKey = LookupKey::fromName($details->displayName)->value;
        $this->phone = $details->phone?->value;
        $this->address = $details->address;
    }

    /**
     * The details a member changes for themselves; name, display name and e-mail address stay with the admin.
     *
     * @throws MemberErased
     */
    public function changeContactDetails(?PhoneNumber $phone, ?string $address): void
    {
        $this->refuseWhenErased();

        $address = \trim($address ?? '');
        $this->phone = $phone?->value;
        $this->address = '' === $address ? null : $address;
    }

    /**
     * @param list<Role> $roles
     *
     * @throws MemberErased
     */
    public function changeRoles(array $roles): void
    {
        $this->refuseWhenErased();

        $this->roles = \array_values(\array_unique(\array_map(static fn(Role $role): string => $role->value, $roles)));
    }

    /**
     * @throws MemberErased
     */
    public function changeContactConsent(bool $consent): void
    {
        $this->refuseWhenErased();

        $this->consentContactShare = $consent;
    }

    /**
     * @throws MemberErased
     */
    public function certify(bool $certified): void
    {
        $this->refuseWhenErased();

        $this->certified = $certified;
    }

    /**
     * @throws MemberErased
     */
    public function changeOpenRoundNotifications(bool $notify): void
    {
        $this->refuseWhenErased();

        $this->notifyOpenRounds = $notify;
    }

    /**
     * @throws MemberErased
     */
    public function allowMcp(bool $allowed): void
    {
        $this->refuseWhenErased();

        $this->mcpAllowed = $allowed;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    /**
     * @throws MemberErased
     */
    public function reactivate(): void
    {
        $this->refuseWhenErased();

        $this->active = true;
    }

    /**
     * Wipes everything that identifies the person. The member stays, inactive and without roles, so its id in rounds,
     * reports and the audit log keeps pointing at a member shown as "removed member".
     */
    public function erase(DateTimeImmutable $now): void
    {
        $this->email = null;
        $this->name = null;
        $this->nameKey = null;
        $this->displayName = null;
        $this->displayNameKey = null;
        $this->phone = null;
        $this->address = null;
        $this->roles = [];
        $this->consentContactShare = false;
        $this->certified = false;
        $this->notifyOpenRounds = false;
        $this->mcpAllowed = false;
        $this->active = false;
        $this->erasedAt ??= $now;
    }

    private function refuseWhenErased(): void
    {
        if ($this->isErased()) {
            throw MemberErased::forMember($this->id);
        }
    }
}
