<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeOpenRoundNotifications;

use App\Membership\Domain\MemberErased;
use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

/**
 * Whether the member hears about a round that seeks a buddy.
 */
#[WriteAction(authorization: 'member.change_open_round_notifications', audit: 'member.open_round_notifications_changed')]
final readonly class ChangeOpenRoundNotificationsHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws MemberErased
     */
    public function __invoke(ChangeOpenRoundNotifications $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $member = $this->members->get(Uuid::fromString($input->memberId));
            $this->authorization->denyUnlessGranted('member.change_open_round_notifications', $member);

            $old = $member->wantsOpenRoundNotifications();
            $member->changeOpenRoundNotifications($input->notify);
            $this->auditTrail->record(new AuditedAction('member.open_round_notifications_changed', 'member', $member->id(), [
                'notifyOpenRounds' => ['old' => $old, 'new' => $input->notify],
            ]));
        });
    }
}
