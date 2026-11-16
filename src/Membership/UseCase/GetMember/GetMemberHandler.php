<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetMember;

use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\ReadAction;
use App\Shared\Domain\Role;
use Symfony\Component\Uid\Uuid;

/**
 * One member with everything the admin manages, deactivated or not.
 */
#[ReadAction]
final readonly class GetMemberHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
    ) {
    }

    /**
     * @throws NotFound
     */
    public function __invoke(GetMember $input): GetMemberOutput
    {
        $this->authorization->denyUnlessGranted('member.view');

        $member = $this->members->get(Uuid::fromString($input->memberId));

        return new GetMemberOutput(
            id: $member->id()->toRfc4122(),
            email: $member->email()?->value,
            name: $member->name(),
            displayName: $member->displayName(),
            phone: $member->phone()?->value,
            address: $member->address(),
            roles: \array_map(static fn(Role $role): string => $role->value, $member->roles()),
            active: $member->isActive(),
            certified: $member->isCertified(),
            consentContactShare: $member->consentsToContactShare(),
            notifyOpenRounds: $member->wantsOpenRoundNotifications(),
        );
    }
}
