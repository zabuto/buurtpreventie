<?php

declare(strict_types=1);

namespace App\Tests\Membership\Fake;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Role;
use Override;
use Symfony\Component\Uid\Uuid;

final class InMemoryMemberRepository implements MemberRepository
{
    /** @var array<string, Member> */
    private array $members = [];

    #[Override]
    public function get(Uuid $id): Member
    {
        return $this->members[$id->toRfc4122()] ?? throw NotFound::forId('member', $id);
    }

    #[Override]
    public function add(Member $member): void
    {
        $this->members[$member->id()->toRfc4122()] = $member;
    }

    #[Override]
    public function getMany(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            $member = $this->members[$id->toRfc4122()] ?? null;
            if (null !== $member) {
                $found[] = $member;
            }
        }

        return $found;
    }

    #[Override]
    public function activeWithOpenRoundNotifications(): array
    {
        return \array_values(\array_filter(
            $this->members,
            static fn(Member $member): bool => $member->isActive() && $member->wantsOpenRoundNotifications(),
        ));
    }

    #[Override]
    public function activeAdminsForUpdate(): array
    {
        return \array_values(\array_filter(
            $this->members,
            static fn(Member $member): bool => $member->isActive() && $member->hasRole(Role::Admin),
        ));
    }

    #[Override]
    public function isEmailAddressTaken(EmailAddress $email, ?Uuid $except = null): bool
    {
        return (
            [] !== \array_filter(
                $this->others($except),
                static fn(Member $member): bool => $member->email()?->value === $email->value,
            )
        );
    }

    #[Override]
    public function isDisplayNameTaken(LookupKey $displayName, ?Uuid $except = null): bool
    {
        return (
            [] !== \array_filter(
                $this->others($except),
                static fn(Member $member): bool => $member->displayNameKey() === $displayName->value,
            )
        );
    }

    #[Override]
    public function search(?LookupKey $term = null): array
    {
        $found = \array_values(\array_filter(
            $this->members,
            static fn(Member $member): bool => (
                !$member->isErased()
                && (
                    null === $term
                    || \str_contains((string) $member->nameKey(), $term->value)
                    || \str_contains((string) $member->displayNameKey(), $term->value)
                )
            ),
        ));
        \usort($found, static fn(Member $a, Member $b): int => \strcmp((string) $a->displayNameKey(), (string) $b->displayNameKey()));

        return $found;
    }

    /**
     * @return list<Member>
     */
    public function all(): array
    {
        return \array_values($this->members);
    }

    /**
     * @return list<Member>
     */
    private function others(?Uuid $except): array
    {
        return \array_values(\array_filter(
            $this->members,
            static fn(Member $member): bool => null === $except || !$member->id()->equals($except),
        ));
    }
}
