<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeOwnContactDetails;

use App\Membership\Domain\InvalidPhoneNumber;
use App\Membership\Domain\Member;
use App\Membership\Domain\MemberErased;
use App\Membership\Domain\MemberRepository;
use App\Membership\Domain\PhoneNumber;
use App\Membership\UseCase\ChangeContactConsent\ChangeContactConsent;
use App\Membership\UseCase\ChangeContactConsent\ChangeContactConsentHandler;
use App\Membership\UseCase\ChangeOpenRoundNotifications\ChangeOpenRoundNotifications;
use App\Membership\UseCase\ChangeOpenRoundNotifications\ChangeOpenRoundNotificationsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\Unchanged;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

/**
 * Changes what a member decides about themselves: phone number, address, consent to share them, and notifications
 * about open rounds. Consent and notifications go through their own use cases, so the audit log records them as
 * the same actions wherever they change. Name, display name and e-mail address stay with the admin: a hijacked
 * session cannot move the address a login code goes to.
 */
#[WriteAction(authorization: 'member.change_contact_details', audit: 'member.contact_details_changed')]
final readonly class ChangeOwnContactDetailsHandler
{
    public function __construct(
        private MemberRepository $members,
        private ChangeContactConsentHandler $changeContactConsent,
        private ChangeOpenRoundNotificationsHandler $changeOpenRoundNotifications,
        private Authorization $authorization,
        private ExecutionContext $executionContext,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws AccessDenied for another member, or nobody acting
     * @throws InvalidPhoneNumber
     * @throws MemberErased
     */
    public function __invoke(ChangeOwnContactDetails $input): void
    {
        $memberId = null === $input->memberId ? $this->executionContext->actor()?->memberId : Uuid::fromString($input->memberId);
        if (null === $memberId) {
            throw AccessDenied::forAction('member.change_contact_details');
        }

        $this->transaction->run(function () use ($input, $memberId): void {
            $member = $this->members->get($memberId);
            $this->authorization->denyUnlessGranted('member.change_contact_details', $member);

            $this->changePhoneAndAddress($member, $input);
            if (!$input->consentContactShare instanceof Unchanged && $input->consentContactShare !== $member->consentsToContactShare()) {
                ($this->changeContactConsent)(new ChangeContactConsent($member->id()->toRfc4122(), $input->consentContactShare));
            }
            if (!$input->notifyOpenRounds instanceof Unchanged && $input->notifyOpenRounds !== $member->wantsOpenRoundNotifications()) {
                ($this->changeOpenRoundNotifications)(new ChangeOpenRoundNotifications($member->id()->toRfc4122(), $input->notifyOpenRounds));
            }
        });
    }

    private function changePhoneAndAddress(Member $member, ChangeOwnContactDetails $input): void
    {
        if ($input->phone instanceof Unchanged && $input->address instanceof Unchanged) {
            return;
        }

        $before = ['phone' => $member->phone()?->value, 'address' => $member->address()];
        $phone = $input->phone instanceof Unchanged ? $before['phone'] : $input->phone;
        $member->changeContactDetails(
            null === $phone ? null : PhoneNumber::fromString($phone),
            $input->address instanceof Unchanged ? $before['address'] : $input->address,
        );

        $after = ['phone' => $member->phone()?->value, 'address' => $member->address()];
        $changes = [];
        foreach ($after as $field => $value) {
            if ($before[$field] === $value) {
                continue;
            }

            $changes[$field] = ['old' => null, 'new' => null];
        }
        if ([] !== $changes) {
            $this->auditTrail->record(new AuditedAction('member.contact_details_changed', 'member', $member->id(), $changes));
        }
    }
}
