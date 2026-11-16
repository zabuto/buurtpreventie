<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Invitation;
use App\Identity\Domain\InvitationRepository;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\MemberIdentity;
use App\Shared\Domain\Transaction;
use App\Shared\Infrastructure\Security\MemberUser;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

/**
 * Accepts an invitation, run by the passport of InvitationAuthenticator after the login throttling. The invitation
 * of a member who is no longer active stays unused, so a reactivated member can still accept it.
 */
final readonly class InvitationCheck
{
    public function __construct(
        private InvitationRepository $invitations,
        private MemberDirectory $members,
        private MemberUserProvider $users,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws LoginRefused invitation_ended for a secret that is unknown, used, expired or replaced, or of a member
     *                      who is no longer active
     */
    public function member(#[SensitiveParameter] string $secret): MemberUser
    {
        /** @var array{string, MemberIdentity|null, Uuid|null} $outcome */
        $outcome = $this->transaction->run(function () use ($secret): array {
            $invitation = $this->invitations->bySecretHashForUpdate(Invitation::hash($secret));
            if (null === $invitation) {
                return ['unknown_invitation', null, null];
            }

            $identity = $this->members->identity($invitation->memberId());
            if (null === $identity || !$identity->active) {
                return ['member_inactive', null, $invitation->memberId()];
            }

            return [$invitation->accept($this->clock->now()) ? 'accepted' : 'invitation_ended', $identity, $invitation->memberId()];
        });
        [$result, $identity, $memberId] = $outcome;

        if ('accepted' !== $result || null === $identity) {
            throw new LoginRefused($result, $memberId, 'invitation_ended');
        }

        return $this->users->userOf($identity);
    }
}
