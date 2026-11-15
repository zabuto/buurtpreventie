<?php

declare(strict_types=1);

namespace App\Membership\UseCase\DeactivateMember;

use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'member.deactivate', audit: 'member.deactivated')]
final readonly class DeactivateMemberHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeactivateMember $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $member = $this->members->get(Uuid::fromString($input->memberId));
            $this->authorization->denyUnlessGranted('member.deactivate', $member);

            $member->deactivate();
            $this->auditTrail->record(new AuditedAction('member.deactivated', 'member', $member->id()));
        });
    }
}
