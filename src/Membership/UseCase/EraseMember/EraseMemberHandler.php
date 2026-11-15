<?php

declare(strict_types=1);

namespace App\Membership\UseCase\EraseMember;

use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'member.erase', audit: 'member.erased')]
final readonly class EraseMemberHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(EraseMember $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $member = $this->members->get(Uuid::fromString($input->memberId));
            $this->authorization->denyUnlessGranted('member.erase', $member);

            $member->erase($this->clock->now());
            $this->auditTrail->record(new AuditedAction('member.erased', 'member', $member->id()));
        });
    }
}
