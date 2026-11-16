<?php

declare(strict_types=1);

namespace App\Membership\UseCase\EraseMember;

use App\Audit\UseCase\EraseActorName\EraseActorName;
use App\Audit\UseCase\EraseActorName\EraseActorNameHandler;
use App\Identity\UseCase\RevokeMemberApiTokens\RevokeMemberApiTokens;
use App\Identity\UseCase\RevokeMemberApiTokens\RevokeMemberApiTokensHandler;
use App\Membership\Domain\LastAdminGuard;
use App\Membership\Domain\MemberRepository;
use App\Patrol\UseCase\WithdrawMemberFromUpcomingRounds\WithdrawMemberFromUpcomingRounds;
use App\Patrol\UseCase\WithdrawMemberFromUpcomingRounds\WithdrawMemberFromUpcomingRoundsHandler;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ReauthenticationRequired;
use App\Shared\Domain\RecentAuthentication;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * An erased member is deactivated as well: it leaves every round that has not started, loses every token and shows
 * in the audit log as "removed member", in the same transaction as the erasure.
 */
#[WriteAction(authorization: 'member.erase', audit: 'member.erased')]
final readonly class EraseMemberHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
        private LastAdminGuard $lastAdmin,
        private WithdrawMemberFromUpcomingRoundsHandler $withdrawFromUpcomingRounds,
        private RevokeMemberApiTokensHandler $revokeApiTokens,
        private EraseActorNameHandler $eraseActorName,
        private RecentAuthentication $recentAuthentication,
    ) {
    }

    public function __invoke(EraseMember $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $member = $this->members->get(Uuid::fromString($input->memberId));
            $this->authorization->denyUnlessGranted('member.erase', $member);
            if (!$this->recentAuthentication->isRecent()) {
                throw ReauthenticationRequired::forAction('member.erase');
            }
            $this->lastAdmin->refuseToLose($member);

            $member->erase($this->clock->now());
            $this->auditTrail->record(new AuditedAction('member.erased', 'member', $member->id()));

            ($this->withdrawFromUpcomingRounds)(new WithdrawMemberFromUpcomingRounds($input->memberId));
            ($this->revokeApiTokens)(new RevokeMemberApiTokens($input->memberId));
            ($this->eraseActorName)(new EraseActorName($input->memberId));
        });
    }
}
