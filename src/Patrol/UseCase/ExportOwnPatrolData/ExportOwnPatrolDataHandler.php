<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ExportOwnPatrolData;

use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundComment;
use App\Patrol\Domain\Round\RoundCommentRepository;
use App\Patrol\Domain\Round\RoundReport;
use App\Patrol\Domain\Round\RoundReportRepository;
use App\Patrol\Domain\Round\RoundRepository;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\MemberProfile;
use App\Shared\Domain\ReadAction;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The rounds the member acting signed up for, and the reports and comments they wrote. Of another member it gives
 * the display name of a buddy and nothing else: the export holds the member's own data.
 */
#[ReadAction]
final readonly class ExportOwnPatrolDataHandler
{
    public function __construct(
        private RoundRepository $rounds,
        private RoundReportRepository $reports,
        private RoundCommentRepository $comments,
        private MemberDirectory $memberDirectory,
        private ExecutionContext $executionContext,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws AccessDenied when nobody acts
     */
    public function __invoke(ExportOwnPatrolData $input): ExportOwnPatrolDataOutput
    {
        $memberId = $this->executionContext->actor()->memberId ?? throw AccessDenied::forAction('patrol.export_own');

        $rounds = $this->rounds->withWalker($memberId);
        $buddyIds = [];
        foreach ($rounds as $round) {
            foreach ($round->walkerIds() as $walkerId) {
                if ($walkerId->equals($memberId)) {
                    continue;
                }

                $buddyIds[$walkerId->toRfc4122()] = $walkerId;
            }
        }
        $profiles = $this->memberDirectory->profilesOf(\array_values($buddyIds));
        $now = $this->clock->now();

        return new ExportOwnPatrolDataOutput(
            \array_map(static fn(Round $round): array => self::round($round, $memberId, $profiles, $now), $rounds),
            \array_map(self::report(...), $this->reports->byAuthor($memberId)),
            \array_map(self::comment(...), $this->comments->byAuthor($memberId)),
        );
    }

    /**
     * @param array<string, MemberProfile> $profiles
     *
     * @return array{roundId: string, startsAt: string, daypart: string, status: string, meetingPoint: string|null, startNote: string|null, joinedAt: string|null, signedUpByCoordinator: bool, buddies: list<string|null>}
     */
    private static function round(Round $round, Uuid $memberId, array $profiles, DateTimeImmutable $now): array
    {
        $participation = $round->participationOf($memberId);
        $buddies = [];
        foreach ($round->walkerIds() as $walkerId) {
            if ($walkerId->equals($memberId)) {
                continue;
            }

            $buddies[] = $profiles[$walkerId->toRfc4122()]->displayName ?? null;
        }

        return [
            'roundId' => $round->id()->toRfc4122(),
            'startsAt' => self::local($round->startsAt()),
            'daypart' => $round->daypart()->value,
            'status' => $round->status($now)->value,
            'meetingPoint' => $round->meetingPoint()?->name(),
            'startNote' => $round->startNote(),
            'joinedAt' => null === $participation ? null : self::local($participation->joinedAt()),
            'signedUpByCoordinator' => null !== $participation?->onBehalfOf(),
            'buddies' => $buddies,
        ];
    }

    /**
     * @return array{roundId: string, roundStartsAt: string, outcome: string, note: string|null, reportedAt: string}
     */
    private static function report(RoundReport $report): array
    {
        return [
            'roundId' => $report->round()->id()->toRfc4122(),
            'roundStartsAt' => self::local($report->round()->startsAt()),
            'outcome' => $report->outcome()->name(),
            'note' => $report->note(),
            'reportedAt' => self::local($report->reportedAt()),
        ];
    }

    /**
     * @return array{roundId: string, text: string, postedAt: string}
     */
    private static function comment(RoundComment $comment): array
    {
        return [
            'roundId' => $comment->round()->id()->toRfc4122(),
            'text' => $comment->text(),
            'postedAt' => self::local($comment->postedAt()),
        ];
    }

    private static function local(DateTimeImmutable $instant): string
    {
        return LocalTime::of($instant)->format(\DATE_ATOM);
    }
}
