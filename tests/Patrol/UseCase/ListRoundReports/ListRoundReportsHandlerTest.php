<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListRoundReports;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\InvalidPeriod;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Patrol\UseCase\ListRoundReports\ListRoundReports;
use App\Patrol\UseCase\ListRoundReports\ListRoundReportsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\MemberProfile;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedMemberDirectory;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ListRoundReportsHandlerTest extends TestCase
{
    private const string CREATED_AT = '2026-10-01T12:00:00+00:00';
    private const string REPORTED_AT = '2026-11-15T09:00:00+00:00';
    private const string FROM = '2026-11-01T00:00:00+01:00';
    private const string TO = '2026-12-01T00:00:00+01:00';

    private InMemoryRoundRepository $rounds;
    private Uuid $jan;
    private Uuid $piet;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->jan = Uuid::v7();
        $this->piet = Uuid::v7();
    }

    #[Test]
    public function it_lists_each_report_with_its_round_in_local_time(): void
    {
        $incident = Outcome::create('Inbraak', OutcomeKind::Incident);
        $round = $this->walkedRound('2026-11-14T21:00:00+00:00', MeetingPoint::create('Speeltuin'));
        $report = $round->submitReport($this->piet, $incident, 'Raam open', new DateTimeImmutable(self::REPORTED_AT));

        $output = $this->handler()(new ListRoundReports(self::FROM, self::TO));

        $this->assertSame(
            [[
                'id' => $report->id()->toRfc4122(),
                'roundId' => $round->id()->toRfc4122(),
                'roundStartsAt' => '2026-11-14T22:00:00+01:00',
                'daypart' => 'evening',
                'meetingPoint' => 'Speeltuin',
                'walkerCount' => 2,
                'outcomeId' => $incident->id()->toRfc4122(),
                'outcome' => 'Inbraak',
                'outcomeKind' => 'incident',
                'note' => 'Raam open',
                'authorDisplayName' => 'Piet V.',
                'reportedAt' => '2026-11-15T10:00:00+01:00',
            ]],
            $output->reports,
        );
    }

    #[Test]
    public function it_lists_only_the_reports_of_rounds_in_the_period_by_start(): void
    {
        $outcome = Outcome::create('Niets te melden', OutcomeKind::NothingToReport);
        $later = $this->walkedRound('2026-11-14T21:00:00+00:00');
        $later->submitReport($this->piet, $outcome, null, new DateTimeImmutable(self::REPORTED_AT));
        $earlier = $this->walkedRound('2026-11-02T21:00:00+00:00');
        $earlier->submitReport($this->piet, $outcome, null, new DateTimeImmutable(self::REPORTED_AT));
        $this->walkedRound('2026-10-30T21:00:00+00:00')->submitReport($this->piet, $outcome, null, new DateTimeImmutable(self::REPORTED_AT));
        $this->walkedRound('2026-11-10T21:00:00+00:00');

        $output = $this->handler()(new ListRoundReports(self::FROM, self::TO));

        $this->assertSame([$earlier->id()->toRfc4122(), $later->id()->toRfc4122()], \array_column($output->reports, 'roundId'));
    }

    #[Test]
    public function it_lists_only_the_reports_with_the_requested_outcome(): void
    {
        $incident = Outcome::create('Inbraak', OutcomeKind::Incident);
        $this->walkedRound('2026-11-02T21:00:00+00:00')->submitReport(
            $this->piet,
            Outcome::create('Rustig', OutcomeKind::NothingToReport),
            null,
            new DateTimeImmutable(self::REPORTED_AT),
        );
        $round = $this->walkedRound('2026-11-03T21:00:00+00:00');
        $round->submitReport($this->piet, $incident, null, new DateTimeImmutable(self::REPORTED_AT));

        $output = $this->handler()(new ListRoundReports(self::FROM, self::TO, $incident->id()->toRfc4122()));

        $this->assertSame([$round->id()->toRfc4122()], \array_column($output->reports, 'roundId'));
    }

    /**
     * The frontend shows a null display name as "removed member".
     */
    #[Test]
    public function it_shows_an_erased_author_without_a_display_name(): void
    {
        $this->walkedRound('2026-11-02T21:00:00+00:00')->submitReport(
            $this->piet,
            Outcome::create('Rustig', OutcomeKind::NothingToReport),
            null,
            new DateTimeImmutable(self::REPORTED_AT),
        );

        $output = $this->handler(new FixedMemberDirectory([new MemberProfile($this->piet, null, null, null, active: false)]))(new ListRoundReports(
            self::FROM,
            self::TO,
        ));

        $this->assertSame([null], \array_column($output->reports, 'authorDisplayName'));
    }

    #[Test]
    public function it_refuses_a_period_longer_than_93_days(): void
    {
        $this->expectException(InvalidPeriod::class);

        $this->handler()(new ListRoundReports('2026-08-01T00:00:00+02:00', '2026-11-03T00:00:00+01:00'));
    }

    #[Test]
    public function it_refuses_a_member_who_may_not_list_the_reports(): void
    {
        $authorization = FakeAuthorization::refusing();

        try {
            $this->handler(authorization: $authorization)(new ListRoundReports(self::FROM, self::TO));
            $this->fail('The list was given.');
        } catch (AccessDenied) {
            $this->assertSame([['report.list', null]], $authorization->asked);
        }
    }

    private function walkedRound(string $startsAt, ?MeetingPoint $meetingPoint = null): Round
    {
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable($startsAt), $meetingPoint),
            $this->jan,
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable(self::CREATED_AT),
        );
        $round->signUp($this->piet, new DateTimeImmutable(self::CREATED_AT));
        $this->rounds->add($round);

        return $round;
    }

    private function handler(?FixedMemberDirectory $directory = null, ?FakeAuthorization $authorization = null): ListRoundReportsHandler
    {
        return new ListRoundReportsHandler(
            $this->rounds,
            $directory ?? new FixedMemberDirectory([FixedMemberDirectory::activeProfile($this->piet, 'Piet V.')]),
            $authorization ?? FakeAuthorization::granting(),
        );
    }
}
