<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\SendReminders;

use App\Patrol\Domain\Reminder;
use App\Patrol\Domain\Reminders;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundRepository;
use App\Patrol\Domain\RoundStatus;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\LocalDay;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\MemberProfile;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Reminds every active walker once of their rounds of the next local calendar day. Each participation remembers its
 * reminder, so a second run on the same day sends nothing.
 */
#[WriteAction(authorization: 'round.send_reminders', audit: 'participation.reminded')]
final readonly class SendRemindersHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private MemberDirectory $members,
        private Reminders $reminders,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Notifier $notifier,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SendReminders $input): void
    {
        $this->authorization->denyUnlessGranted('round.send_reminders');

        $this->transaction->run(function (): void {
            $now = $this->clock->now();
            $tomorrow = LocalDay::of($now)->next();
            $rounds = $this->rounds->between($tomorrow->startsAt(), $tomorrow->endsAt());

            foreach ($this->reminders->due($rounds, $this->activeWalkerIds($rounds)) as $reminder) {
                $this->remind($reminder, $now);
            }
        });
    }

    /**
     * @param list<Round> $rounds
     *
     * @return list<Uuid>
     */
    private function activeWalkerIds(array $rounds): array
    {
        $walkerIds = [];
        foreach ($rounds as $round) {
            foreach ($round->walkerIds() as $walkerId) {
                $walkerIds[$walkerId->toRfc4122()] = $walkerId;
            }
        }

        $active = \array_filter($this->members->profilesOf(\array_values($walkerIds)), static fn(MemberProfile $profile): bool => $profile->active);

        return \array_values(\array_map(static fn(MemberProfile $profile): Uuid => $profile->memberId, $active));
    }

    private function remind(Reminder $reminder, DateTimeImmutable $now): void
    {
        $seekingBuddy = \array_filter($reminder->rounds, static fn(Round $round): bool => RoundStatus::SeekingBuddy === $round->status($now));

        $this->notifier->send(new Notification($reminder->memberId, 'round.reminder', [
            'roundIds' => self::idList($reminder->rounds),
            'seekingBuddyRoundIds' => self::idList($seekingBuddy),
        ]));

        foreach ($reminder->rounds as $round) {
            $round->markReminded($reminder->memberId, $now);
            $this->auditTrail->record(new AuditedAction('participation.reminded', 'round', $round->id(), [
                'walker' => ['old' => null, 'new' => $reminder->memberId->toRfc4122()],
            ]));
        }
    }

    /**
     * Notification parameters hold only strings and integers, so the ids travel as one comma-separated string.
     *
     * @param array<Round> $rounds
     */
    private static function idList(array $rounds): string
    {
        return \implode(',', \array_map(static fn(Round $round): string => $round->id()->toRfc4122(), $rounds));
    }
}
