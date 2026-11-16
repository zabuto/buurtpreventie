<?php

declare(strict_types=1);

namespace App\Membership\UseCase\InviteMember;

use App\Identity\UseCase\IssueInvitation\IssueInvitation;
use App\Identity\UseCase\IssueInvitation\IssueInvitationHandler;
use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

/**
 * Has a member invited by mail, such as a new member or one whose invitation got lost. The invitation itself, and
 * its audit entry, belong to Identity.
 */
#[WriteAction(authorization: 'member.invite', audit: 'account.invitation_issued')]
final readonly class InviteMemberHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
        private IssueInvitationHandler $issueInvitation,
    ) {
    }

    public function __invoke(InviteMember $input): InviteMemberOutput
    {
        $this->authorization->denyUnlessGranted('member.invite');
        $member = $this->members->get(Uuid::fromString($input->memberId));

        return new InviteMemberOutput(($this->issueInvitation)(new IssueInvitation($member->id()->toRfc4122()))->secret);
    }
}
