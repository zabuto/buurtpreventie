<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\GetRound;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Round\Participation;
use App\Patrol\Domain\Round\ReportReadingPolicy;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundComment;
use App\Patrol\Domain\Round\RoundCommentRepository;
use App\Patrol\Domain\Round\RoundPolicy;
use App\Patrol\Domain\Round\RoundReport;
use App\Patrol\Domain\Round\RoundRepository;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\MemberProfile;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\ReadAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Shows one round with its walkers, report and comments. Only Membership knows who is inactive, so inactive walkers
 * show as a count. A coordinator or admin, or the system itself, sees full names; the walkers of the round and
 * coordinators see the phone numbers walkers consent to share. The note of the report and the comments are for the
 * walkers of the round, analysts and coordinators only; anyone else sees the outcome without a note.
 */
#[ReadAction]
final readonly class GetRoundHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private RoundCommentRepository $comments,
        private MemberDirectory $memberDirectory,
        private RoundPolicy $policy,
        private ReportReadingPolicy $readingPolicy,
        private Authorization $authorization,
        private ExecutionContext $executionContext,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws NotFound
     */
    public function __invoke(GetRound $input): GetRoundOutput
    {
        $round = $this->rounds->get(Uuid::fromString($input->roundId));
        $this->authorization->denyUnlessGranted('round.view', $round);

        $now = $this->clock->now();
        $report = $round->report();
        $withNotes = $this->readingPolicy->maySeeNotes($this->executionContext->actor(), $round);
        $comments = $withNotes ? $this->comments->forRound($round) : [];
        $walkers = $this->walkers($round);
        $authors = $this->memberDirectory->profilesOf([
            ...(null === $report ? [] : [$report->authorId()]),
            ...\array_map(static fn(RoundComment $comment): Uuid => $comment->authorId(), $comments),
        ]);

        return new GetRoundOutput(
            id: $round->id()->toRfc4122(),
            startsAt: LocalTime::of($round->startsAt())->format(\DATE_ATOM),
            daypart: $round->daypart()->value,
            status: $round->status($now)->value,
            walkerCount: $round->walkerCount(),
            minimumWalkers: $round->staffingRule()->minimumWalkers,
            maximumWalkers: $round->staffingRule()->maximumWalkers,
            openSpots: $round->openSpots($now),
            meetingPoint: self::meetingPoint($round->meetingPoint()),
            startNote: $round->startNote(),
            cancelReason: $round->cancellation()->reason(),
            ownerId: $round->ownerId()?->toRfc4122(),
            walkers: $walkers,
            inactiveWalkerCount: $round->walkerCount() - \count($walkers),
            report: null === $report ? null : self::report($report, $authors, $withNotes),
            comments: \array_map(static fn(RoundComment $comment): array => self::comment($comment, $authors), $comments),
        );
    }

    /**
     * @return list<array{id: string, displayName: string|null, name?: string|null, phone?: string|null}>
     */
    private function walkers(Round $round): array
    {
        $actor = $this->executionContext->actor();
        $withFullNames = $this->policy->maySeeFullNames($actor);
        $withPhones = null !== $actor && $this->policy->maySeeWalkerPhones($actor, $round);

        $profiles = $this->memberDirectory->profilesOf($round->walkerIds());
        $walkers = [];
        foreach (self::byJoining($round) as $walkerId) {
            $profile = $profiles[$walkerId->toRfc4122()] ?? null;
            if (null === $profile || !$profile->active) {
                continue;
            }

            $walkers[] = self::walker($profile, $withFullNames, $withPhones);
        }

        return $walkers;
    }

    /**
     * @return list<Uuid> earliest joined first
     */
    private static function byJoining(Round $round): array
    {
        $participations = \array_values(\array_filter(\array_map(
            $round->participationOf(...),
            $round->walkerIds(),
        )));
        \usort($participations, static fn(Participation $a, Participation $b): int => $a->joinedBefore($b) ? -1 : 1);

        return \array_map(static fn(Participation $participation): Uuid => $participation->memberId(), $participations);
    }

    /**
     * @return array{id: string, displayName: string|null, name?: string|null, phone?: string|null}
     */
    private static function walker(MemberProfile $profile, bool $withFullName, bool $withPhone): array
    {
        $walker = ['id' => $profile->memberId->toRfc4122(), 'displayName' => $profile->displayName];
        if ($withFullName) {
            $walker['name'] = $profile->name;
        }

        if ($withPhone) {
            $walker['phone'] = $profile->phone;
        }

        return $walker;
    }

    /**
     * @return array{id: string, name: string, latitude: float|null, longitude: float|null}|null
     */
    private static function meetingPoint(?MeetingPoint $meetingPoint): ?array
    {
        if (null === $meetingPoint) {
            return null;
        }

        return [
            'id' => $meetingPoint->id()->toRfc4122(),
            'name' => $meetingPoint->name(),
            'latitude' => $meetingPoint->coordinates()?->latitude,
            'longitude' => $meetingPoint->coordinates()?->longitude,
        ];
    }

    /**
     * @param array<string, MemberProfile> $authors
     *
     * @return array{
     *     id: string,
     *     outcome: string,
     *     outcomeKind: string,
     *     note: string|null,
     *     authorId: string,
     *     authorDisplayName: string|null,
     *     reportedAt: string,
     * }
     */
    private static function report(RoundReport $report, array $authors, bool $withNote): array
    {
        return [
            'id' => $report->id()->toRfc4122(),
            'outcome' => $report->outcome()->name(),
            'outcomeKind' => $report->outcome()->kind()->value,
            'note' => $withNote ? $report->note() : null,
            'authorId' => $report->authorId()->toRfc4122(),
            'authorDisplayName' => $authors[$report->authorId()->toRfc4122()]->displayName ?? null,
            'reportedAt' => LocalTime::of($report->reportedAt())->format(\DATE_ATOM),
        ];
    }

    /**
     * @param array<string, MemberProfile> $authors
     *
     * @return array{id: string, authorId: string, authorDisplayName: string|null, text: string, postedAt: string}
     */
    private static function comment(RoundComment $comment, array $authors): array
    {
        return [
            'id' => $comment->id()->toRfc4122(),
            'authorId' => $comment->authorId()->toRfc4122(),
            'authorDisplayName' => $authors[$comment->authorId()->toRfc4122()]->displayName ?? null,
            'text' => $comment->text(),
            'postedAt' => LocalTime::of($comment->postedAt())->format(\DATE_ATOM),
        ];
    }
}
