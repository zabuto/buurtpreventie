<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeMemberRoles;

use App\Membership\Domain\LastAdminGuard;
use App\Membership\Domain\Member;
use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Role;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'member.change_roles', audit: 'member.roles_changed')]
final readonly class ChangeMemberRolesHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private LastAdminGuard $lastAdmin,
    ) {
    }

    public function __invoke(ChangeMemberRoles $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $member = $this->members->get(Uuid::fromString($input->memberId));
            $this->authorization->denyUnlessGranted('member.change_roles', $member);

            $roles = \array_map(Role::from(...), $input->roles);
            if (!\in_array(Role::Admin, $roles, strict: true)) {
                $this->lastAdmin->refuseToLose($member);
            }

            $old = self::roles($member);
            $member->changeRoles($roles);
            $this->auditTrail->record(new AuditedAction('member.roles_changed', 'member', $member->id(), [
                'roles' => ['old' => $old, 'new' => self::roles($member)],
            ]));
        });
    }

    private static function roles(Member $member): string
    {
        return \implode(',', \array_map(static fn(Role $role): string => $role->value, $member->roles()));
    }
}
