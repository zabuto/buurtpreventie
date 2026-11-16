<?php

declare(strict_types=1);

namespace App\Membership\Infrastructure;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\MemberIdentity;
use App\Shared\Domain\MemberProfile;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Role;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class RepositoryMemberDirectory implements MemberDirectory
{
    public function __construct(
        private MemberRepository $members,
    ) {
    }

    #[Override]
    public function isActive(Uuid $memberId): bool
    {
        try {
            return $this->members->get($memberId)->isActive();
        } catch (NotFound) {
            return false;
        }
    }

    #[Override]
    public function openRoundSubscribers(): array
    {
        $walkers = \array_filter(
            $this->members->activeWithOpenRoundNotifications(),
            static fn(Member $member): bool => $member->hasRole(Role::Walker),
        );

        return \array_values(\array_map(static fn(Member $member): Uuid => $member->id(), $walkers));
    }

    #[Override]
    public function profilesOf(array $memberIds): array
    {
        $profiles = [];
        foreach ($this->members->getMany($memberIds) as $member) {
            $profiles[$member->id()->toRfc4122()] = new MemberProfile(
                $member->id(),
                $member->displayName(),
                $member->name(),
                $member->consentsToContactShare() ? $member->phone()?->value : null,
                $member->isActive(),
            );
        }

        return $profiles;
    }

    #[Override]
    public function identity(Uuid $memberId): ?MemberIdentity
    {
        try {
            $member = $this->members->get($memberId);
        } catch (NotFound) {
            return null;
        }

        return self::identityOf($member);
    }

    #[Override]
    public function identify(EmailAddress $email): ?MemberIdentity
    {
        $member = $this->members->ofEmail($email);

        return null === $member ? null : self::identityOf($member);
    }

    private static function identityOf(Member $member): MemberIdentity
    {
        return new MemberIdentity($member->id(), $member->roles(), $member->isActive());
    }
}
