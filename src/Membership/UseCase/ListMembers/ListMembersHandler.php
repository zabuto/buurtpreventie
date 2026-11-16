<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ListMembers;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\ReadAction;
use App\Shared\Domain\Role;

/**
 * Lists every member the admin manages by display name, deactivated ones included; an erased member has nothing
 * left to manage.
 */
#[ReadAction]
final readonly class ListMembersHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
    ) {
    }

    public function __invoke(ListMembers $input): ListMembersOutput
    {
        $this->authorization->denyUnlessGranted('member.view');

        $search = \trim($input->search ?? '');

        return new ListMembersOutput(\array_map(
            self::record(...),
            $this->members->search('' === $search ? null : LookupKey::fromName($search)),
        ));
    }

    /**
     * @return array{
     *     id: string,
     *     email: string|null,
     *     name: string|null,
     *     displayName: string|null,
     *     phone: string|null,
     *     address: string|null,
     *     roles: list<string>,
     *     active: bool,
     *     certified: bool,
     *     consentContactShare: bool,
     *     notifyOpenRounds: bool,
     * }
     */
    private static function record(Member $member): array
    {
        return [
            'id' => $member->id()->toRfc4122(),
            'email' => $member->email()?->value,
            'name' => $member->name(),
            'displayName' => $member->displayName(),
            'phone' => $member->phone()?->value,
            'address' => $member->address(),
            'roles' => \array_map(static fn(Role $role): string => $role->value, $member->roles()),
            'active' => $member->isActive(),
            'certified' => $member->isCertified(),
            'consentContactShare' => $member->consentsToContactShare(),
            'notifyOpenRounds' => $member->wantsOpenRoundNotifications(),
        ];
    }
}
