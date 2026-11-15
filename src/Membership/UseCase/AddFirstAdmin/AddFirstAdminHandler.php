<?php

declare(strict_types=1);

namespace App\Membership\UseCase\AddFirstAdmin;

use App\Membership\Domain\AdminExists;
use App\Membership\Domain\FirstAdminGuard;
use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Membership\Domain\MemberRepository;
use App\Membership\Domain\PhoneNumber;
use App\Membership\Domain\UniqueMemberDetails;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\Role;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;

/**
 * The system adds the admin a fresh installation starts with, for the installer and the fixtures. Without an active
 * admin nobody can add members, so this is the only way in.
 */
#[WriteAction(authorization: 'member.add_first_admin', audit: 'member.added')]
final readonly class AddFirstAdminHandler
{
    public function __construct(
        private MemberRepository $members,
        private FirstAdminGuard $firstAdminGuard,
        private UniqueMemberDetails $uniqueMemberDetails,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws AdminExists
     */
    public function __invoke(AddFirstAdmin $input): AddFirstAdminOutput
    {
        $this->authorization->denyUnlessGranted('member.add_first_admin');

        $details = new MemberDetails(
            EmailAddress::fromString($input->email),
            $input->name,
            $input->displayName,
            null === $input->phone ? null : PhoneNumber::fromString($input->phone),
            $input->address,
        );

        return $this->transaction->run(function () use ($details): AddFirstAdminOutput {
            $this->firstAdminGuard->refuseWhileAnAdminIsActive();
            $this->uniqueMemberDetails->ensureAvailable($details);

            $member = Member::register($details);
            $member->changeRoles([Role::Admin]);
            $this->members->add($member);

            $this->auditTrail->record(new AuditedAction('member.added', 'member', $member->id(), [
                'displayName' => ['old' => null, 'new' => $member->displayName()],
                'roles' => ['old' => null, 'new' => Role::Admin->value],
            ]));

            return new AddFirstAdminOutput($member->id()->toRfc4122());
        });
    }
}
