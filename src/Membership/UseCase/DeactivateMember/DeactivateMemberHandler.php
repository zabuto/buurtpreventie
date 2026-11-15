<?php

declare(strict_types=1);

namespace App\Membership\UseCase\DeactivateMember;

use App\Identity\UseCase\RevokeMemberApiTokens\RevokeMemberApiTokens;
use App\Identity\UseCase\RevokeMemberApiTokens\RevokeMemberApiTokensHandler;
use App\Membership\Domain\LastAdminGuard;
use App\Membership\Domain\MemberRepository;
use App\Patrol\UseCase\WithdrawMemberFromUpcomingRounds\WithdrawMemberFromUpcomingRounds;
use App\Patrol\UseCase\WithdrawMemberFromUpcomingRounds\WithdrawMemberFromUpcomingRoundsHandler;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

/**
 * A deactivated member can neither walk nor log in: it leaves every round that has not started and loses every
 * token in the same transaction, so a deactivated member never keeps a working token. The password stays for a
 * reactivation.
 */
#[WriteAction(authorization: 'member.deactivate', audit: 'member.deactivated')]
final readonly class DeactivateMemberHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private LastAdminGuard $lastAdmin,
        private WithdrawMemberFromUpcomingRoundsHandler $withdrawFromUpcomingRounds,
        private RevokeMemberApiTokensHandler $revokeApiTokens,
    ) {
    }

    public function __invoke(DeactivateMember $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $member = $this->members->get(Uuid::fromString($input->memberId));
            $this->authorization->denyUnlessGranted('member.deactivate', $member);
            $this->lastAdmin->refuseToLose($member);

            $member->deactivate();
            $this->auditTrail->record(new AuditedAction('member.deactivated', 'member', $member->id()));

            ($this->withdrawFromUpcomingRounds)(new WithdrawMemberFromUpcomingRounds($input->memberId));
            ($this->revokeApiTokens)(new RevokeMemberApiTokens($input->memberId));
        });
    }
}
