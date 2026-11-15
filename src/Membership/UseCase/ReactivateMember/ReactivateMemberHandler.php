<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ReactivateMember;

use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'member.reactivate', audit: 'member.reactivated')]
final readonly class ReactivateMemberHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ReactivateMember $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $member = $this->members->get(Uuid::fromString($input->memberId));
            $this->authorization->denyUnlessGranted('member.reactivate', $member);

            $member->reactivate();
            $this->auditTrail->record(new AuditedAction('member.reactivated', 'member', $member->id()));
        });
    }
}
