<?php

declare(strict_types=1);

namespace App\Identity\UseCase\IssueInvitation;

use App\Identity\Domain\AccountRepository;
use App\Identity\Domain\CredentialPolicy;
use App\Identity\Domain\Invitation;
use App\Identity\Domain\InvitationRepository;
use App\Identity\Domain\InviteeInactive;
use App\Identity\Domain\SecretGenerator;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Mails a member a link that logs them in once, and ends the member's earlier invitations. The secret comes back
 * only to an admin inviting a member who never logged in: for anyone else the link would log the admin in as a
 * member who has a way in of their own.
 */
#[WriteAction(authorization: 'account.issue_invitation', audit: 'account.invitation_issued')]
final readonly class IssueInvitationHandler
{
    public function __construct(
        private MemberDirectory $members,
        private InvitationRepository $invitations,
        private AccountRepository $accounts,
        private SecretGenerator $secretGenerator,
        private Notifier $notifier,
        private Authorization $authorization,
        private ExecutionContext $executionContext,
        private CredentialPolicy $policy,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws NotFound for an unknown member
     * @throws InviteeInactive for a deactivated or erased member
     */
    public function __invoke(IssueInvitation $input): IssueInvitationOutput
    {
        $memberId = Uuid::fromString($input->memberId);
        $this->authorization->denyUnlessGranted('account.issue_invitation', $memberId);

        $identity = $this->members->identity($memberId) ?? throw NotFound::forId('member', $memberId);
        if (!$identity->active) {
            throw InviteeInactive::forMember($memberId);
        }

        $secret = $this->secretGenerator->newInvitationSecret();
        $now = $this->clock->now();
        $this->transaction->run(function () use ($memberId, $secret, $now): void {
            foreach ($this->invitations->openOfMember($memberId, $now) as $earlier) {
                $earlier->end($now);
            }

            $this->invitations->add(Invitation::issue($memberId, $secret, $now));
            $this->auditTrail->record(new AuditedAction('account.invitation_issued', 'member', $memberId));
            $this->notifier->send(new Notification($memberId, 'account.invitation', [
                'secret' => $secret,
                'hours' => Invitation::VALIDITY_IN_HOURS,
            ]));
        });

        return new IssueInvitationOutput($this->mayHandOn($memberId) ? $secret : null);
    }

    private function mayHandOn(Uuid $memberId): bool
    {
        $actor = $this->executionContext->actor();

        return (
            null !== $actor
                && $this->policy->maySeeInvitationLink($actor)
                && null === $this->accounts->ofMember($memberId)?->lastLoginAt()
        );
    }
}
