<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeContactConsent;

use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'member.change_consent', audit: 'member.consent_changed')]
final readonly class ChangeContactConsentHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ChangeContactConsent $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $member = $this->members->get(Uuid::fromString($input->memberId));
            $this->authorization->denyUnlessGranted('member.change_consent', $member);

            $old = $member->consentsToContactShare();
            $member->changeContactConsent($input->consent);
            $this->auditTrail->record(new AuditedAction('member.consent_changed', 'member', $member->id(), [
                'consentContactShare' => ['old' => $old, 'new' => $input->consent],
            ]));
        });
    }
}
