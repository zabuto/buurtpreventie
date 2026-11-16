<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetOwnProfile;

use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\ReadAction;
use App\Shared\Domain\Role;

/**
 * Everything the app keeps in the profile of the member acting, contact details included: they are the member's own.
 */
#[ReadAction]
final readonly class GetOwnProfileHandler
{
    public function __construct(
        private MemberRepository $members,
        private ExecutionContext $executionContext,
    ) {
    }

    /**
     * @throws AccessDenied when nobody acts
     */
    public function __invoke(GetOwnProfile $input): GetOwnProfileOutput
    {
        $memberId = $this->executionContext->actor()->memberId ?? throw AccessDenied::forAction('member.view_own');
        $member = $this->members->get($memberId);

        return new GetOwnProfileOutput(
            id: $member->id()->toRfc4122(),
            email: $member->email()?->value,
            name: $member->name(),
            displayName: $member->displayName(),
            phone: $member->phone()?->value,
            address: $member->address(),
            roles: \array_map(static fn(Role $role): string => $role->value, $member->roles()),
            consentContactShare: $member->consentsToContactShare(),
            notifyOpenRounds: $member->wantsOpenRoundNotifications(),
            certified: $member->isCertified(),
        );
    }
}
