<?php

declare(strict_types=1);

namespace App\Membership\UseCase\AddMember;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Membership\Domain\MemberRepository;
use App\Membership\Domain\UniqueMemberDetails;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\PhoneNumber;
use App\Shared\Domain\Role;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;

#[WriteAction(authorization: 'member.add', audit: 'member.added')]
final readonly class AddMemberHandler
{
    public function __construct(
        private MemberRepository $members,
        private UniqueMemberDetails $uniqueMemberDetails,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(AddMember $input): AddMemberOutput
    {
        $this->authorization->denyUnlessGranted('member.add');

        $details = new MemberDetails(
            EmailAddress::fromString($input->email),
            $input->name,
            $input->displayName,
            null === $input->phone ? null : PhoneNumber::fromString($input->phone),
            $input->address,
        );
        $roles = \array_map(Role::from(...), $input->roles);

        return $this->transaction->run(function () use ($details, $roles): AddMemberOutput {
            $this->uniqueMemberDetails->ensureAvailable($details);

            $member = Member::register($details);
            $member->changeRoles($roles);
            $this->members->add($member);

            // Contact details stay out of the audit log, so it never outlives an erasure with them.
            $this->auditTrail->record(new AuditedAction('member.added', 'member', $member->id(), [
                'displayName' => ['old' => null, 'new' => $member->displayName()],
                'roles' => ['old' => null, 'new' => \implode(',', \array_map(static fn(Role $role): string => $role->value, $member->roles()))],
            ]));

            return new AddMemberOutput($member->id()->toRfc4122());
        });
    }
}
