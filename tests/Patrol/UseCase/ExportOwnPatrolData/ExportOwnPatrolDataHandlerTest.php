<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ExportOwnPatrolData;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundComment;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Patrol\UseCase\ExportOwnPatrolData\ExportOwnPatrolData;
use App\Patrol\UseCase\ExportOwnPatrolData\ExportOwnPatrolDataHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\MemberProfile;
use App\Tests\Patrol\Fake\InMemoryRoundCommentRepository;
use App\Tests\Patrol\Fake\InMemoryRoundReportRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FixedExecutionContext;
use App\Tests\Shared\Fake\FixedMemberDirectory;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class ExportOwnPatrolDataHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';
    private const string CREATED_AT = '2026-11-01T12:00:00+00:00';

    private InMemoryRoundRepository $rounds;
    private InMemoryRoundCommentRepository $comments;
    private Uuid $jan;
    private Uuid $piet;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->comments = new InMemoryRoundCommentRepository();
        $this->jan = Uuid::v7();
        $this->piet = Uuid::v7();
    }

    #[Test]
    public function it_gives_the_rounds_of_the_member_with_buddies_by_display_name_only(): void
    {
        $round = $this->round('2026-11-14T20:00:00+00:00', $this->piet, MeetingPoint::create('Speeltuin'));
        $round->signUp($this->jan, new DateTimeImmutable('2026-11-02T12:00:00+00:00'));
        $this->round('2026-11-16T20:00:00+00:00', $this->piet);

        $output = $this->handler()(new ExportOwnPatrolData());

        $this->assertSame(
            [[
                'roundId' => $round->id()->toRfc4122(),
                'startsAt' => '2026-11-14T21:00:00+01:00',
                'daypart' => 'evening',
                'status' => 'walked',
                'meetingPoint' => 'Speeltuin',
                'startNote' => null,
                'joinedAt' => '2026-11-02T13:00:00+01:00',
                'signedUpByCoordinator' => false,
                'buddies' => ['Piet V.'],
            ]],
            $output->rounds,
        );
    }

    #[Test]
    public function it_shows_an_erased_buddy_without_a_name(): void
    {
        $this->round('2026-11-14T20:00:00+00:00', $this->jan)->signUp($this->piet, new DateTimeImmutable(self::CREATED_AT));

        $output = $this->handler(new FixedMemberDirectory([new MemberProfile($this->piet, null, null, null, active: false)]))(new ExportOwnPatrolData());

        $this->assertSame([null], $output->rounds[0]['buddies'] ?? []);
    }

    #[Test]
    public function it_gives_the_reports_and_comments_the_member_wrote_and_not_those_of_others(): void
    {
        $round = $this->round('2026-11-14T20:00:00+00:00', $this->jan);
        $round->signUp($this->piet, new DateTimeImmutable(self::CREATED_AT));
        $round->submitReport($this->jan, Outcome::create('Opmerkingen', OutcomeKind::Remarks), 'Rustig', new DateTimeImmutable(self::NOW));
        $this->comments->add(RoundComment::post($round, $this->jan, 'Mooie avond.', new DateTimeImmutable(self::NOW)));
        $this->comments->add(RoundComment::post($round, $this->piet, 'Tot de volgende keer.', new DateTimeImmutable(self::NOW)));

        $output = $this->handler()(new ExportOwnPatrolData());

        $this->assertSame(
            [
                [[
                    'roundId' => $round->id()->toRfc4122(),
                    'roundStartsAt' => '2026-11-14T21:00:00+01:00',
                    'outcome' => 'Opmerkingen',
                    'note' => 'Rustig',
                    'reportedAt' => '2026-11-15T13:00:00+01:00',
                ]],
                [['roundId' => $round->id()->toRfc4122(), 'text' => 'Mooie avond.', 'postedAt' => '2026-11-15T13:00:00+01:00']],
            ],
            [$output->reports, $output->comments],
        );
    }

    #[Test]
    public function it_refuses_when_nobody_acts(): void
    {
        $this->expectException(AccessDenied::class);

        $this->handler(withActor: false)(new ExportOwnPatrolData());
    }

    private function round(string $startsAt, Uuid $firstWalker, ?MeetingPoint $meetingPoint = null): Round
    {
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt), $meetingPoint),
            $firstWalker,
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable(self::CREATED_AT),
        );
        $this->rounds->add($round);

        return $round;
    }

    private function handler(?FixedMemberDirectory $directory = null, bool $withActor = true): ExportOwnPatrolDataHandler
    {
        return new ExportOwnPatrolDataHandler(
            $this->rounds,
            new InMemoryRoundReportRepository($this->rounds),
            $this->comments,
            $directory ?? new FixedMemberDirectory([FixedMemberDirectory::activeProfile($this->piet, 'Piet V.')]),
            new FixedExecutionContext($withActor ? new Actor($this->jan, []) : null),
            new MockClock(self::NOW),
        );
    }
}
