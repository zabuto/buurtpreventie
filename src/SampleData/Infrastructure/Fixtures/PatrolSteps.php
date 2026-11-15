<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use App\Patrol\UseCase\AddRoundComment\AddRoundComment;
use App\Patrol\UseCase\AddRoundComment\AddRoundCommentHandler;
use App\Patrol\UseCase\AddRoundComment\AddRoundCommentOutput;
use App\Patrol\UseCase\CancelRound\CancelRound;
use App\Patrol\UseCase\CancelRound\CancelRoundHandler;
use App\Patrol\UseCase\CreateMeetingPoint\CreateMeetingPoint;
use App\Patrol\UseCase\CreateMeetingPoint\CreateMeetingPointHandler;
use App\Patrol\UseCase\CreateMeetingPoint\CreateMeetingPointOutput;
use App\Patrol\UseCase\CreateOutcome\CreateOutcome;
use App\Patrol\UseCase\CreateOutcome\CreateOutcomeHandler;
use App\Patrol\UseCase\CreateOutcome\CreateOutcomeOutput;
use App\Patrol\UseCase\CreateRound\CreateRound;
use App\Patrol\UseCase\CreateRound\CreateRoundHandler;
use App\Patrol\UseCase\CreateRound\CreateRoundOutput;
use App\Patrol\UseCase\ReportRoundResult\ReportRoundResult;
use App\Patrol\UseCase\ReportRoundResult\ReportRoundResultHandler;
use App\Patrol\UseCase\ReportRoundResult\ReportRoundResultOutput;
use App\Patrol\UseCase\SignUpForRound\SignUpForRound;
use App\Patrol\UseCase\SignUpForRound\SignUpForRoundHandler;
use App\Patrol\UseCase\WithdrawFromRound\WithdrawFromRound;
use App\Patrol\UseCase\WithdrawFromRound\WithdrawFromRoundHandler;
use App\Shared\Domain\Channel;
use App\Shared\Infrastructure\Security\RunAs;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Catalogues and rounds through their use cases, each run by the member who would take the step.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final readonly class PatrolSteps
{
    public const string NOTHING_TO_REPORT = 'nothing_to_report';
    public const string REMARKS = 'remarks';
    public const string INCIDENT = 'incident';

    public function __construct(
        private Timeline $timeline,
        private RunAs $runAs,
        private CreateMeetingPointHandler $createMeetingPoint,
        private CreateOutcomeHandler $createOutcome,
        private CreateRoundHandler $createRound,
        private SignUpForRoundHandler $signUpForRound,
        private WithdrawFromRoundHandler $withdrawFromRound,
        private ReportRoundResultHandler $reportRoundResult,
        private AddRoundCommentHandler $addRoundComment,
        private CancelRoundHandler $cancelRound,
    ) {
    }

    public function meetingPoint(SampleMember $admin, DateTimeImmutable $at, string $name): string
    {
        return $this->as(
            $admin,
            $at,
            fn(): CreateMeetingPointOutput => ($this->createMeetingPoint)(new CreateMeetingPoint($name, null, null)),
        )->id;
    }

    /**
     * @return array{nothing_to_report: string, remarks: string, incident: string} the outcome id by kind
     */
    public function standardOutcomes(SampleMember $admin, DateTimeImmutable $at): array
    {
        $create = fn(string $name, string $kind): string => $this->as(
            $admin,
            $at,
            fn(): CreateOutcomeOutput => ($this->createOutcome)(new CreateOutcome($name, $kind)),
        )->id;

        return [
            self::NOTHING_TO_REPORT => $create('Niets te melden', self::NOTHING_TO_REPORT),
            self::REMARKS => $create('Opmerkingen', self::REMARKS),
            self::INCIDENT => $create('Incident', self::INCIDENT),
        ];
    }

    /**
     * @param SampleMember|null $firstWalker the member a coordinator creates the round for, or null for the creator
     */
    public function createRound(
        SampleMember $creator,
        DateTimeImmutable $at,
        DateTimeImmutable $startsAt,
        ?string $meetingPointId,
        ?SampleMember $firstWalker = null,
    ): string {
        $input = new CreateRound(startsAt: $startsAt->format(\DATE_ATOM), firstWalkerId: $firstWalker?->id, meetingPointId: $meetingPointId);

        return $this->as($creator, $at, fn(): CreateRoundOutput => ($this->createRound)($input))->id;
    }

    public function signUp(SampleMember $walker, DateTimeImmutable $at, string $roundId): void
    {
        $this->as($walker, $at, fn() => ($this->signUpForRound)(new SignUpForRound($roundId)));
    }

    public function withdraw(SampleMember $walker, DateTimeImmutable $at, string $roundId): void
    {
        $this->as($walker, $at, fn() => ($this->withdrawFromRound)(new WithdrawFromRound($roundId)));
    }

    public function report(SampleMember $walker, DateTimeImmutable $at, string $roundId, string $outcomeId, ?string $note): void
    {
        $this->as($walker, $at, fn(): ReportRoundResultOutput => ($this->reportRoundResult)(new ReportRoundResult(
            $roundId,
            $outcomeId,
            $note,
        )));
    }

    public function comment(SampleMember $walker, DateTimeImmutable $at, string $roundId, string $text): void
    {
        $this->as($walker, $at, fn(): AddRoundCommentOutput => ($this->addRoundComment)(new AddRoundComment(
            $roundId,
            $text,
        )));
    }

    public function cancel(SampleMember $walker, DateTimeImmutable $at, string $roundId, string $reason): void
    {
        $this->as($walker, $at, fn() => ($this->cancelRound)(new CancelRound($roundId, $reason)));
    }

    /**
     * @template T
     *
     * @param callable(): T $step
     *
     * @return T
     */
    private function as(SampleMember $member, DateTimeImmutable $at, callable $step): mixed
    {
        return $this->timeline->at($at, fn(): mixed => $this->runAs->member($member->actor, Channel::Console, $step));
    }
}
