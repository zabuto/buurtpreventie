<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\GetRound;

use App\Patrol\Domain\Catalogue\Coordinates;
use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\ReportReadingPolicy;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundComment;
use App\Patrol\Domain\Round\RoundPolicy;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Patrol\UseCase\GetRound\GetRound;
use App\Patrol\UseCase\GetRound\GetRoundHandler;
use App\Patrol\UseCase\GetRound\GetRoundOutput;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\MemberProfile;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Fake\InMemoryRoundCommentRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use App\Tests\Shared\Fake\FixedMemberDirectory;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class GetRoundHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';
    private const string CREATED_AT = '2026-11-01T12:00:00+00:00';
    private const string UPCOMING = '2026-11-16T21:00:00+00:00';
    private const string PAST = '2026-11-14T21:00:00+00:00';

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

    /**
     * @return iterable<string, array{Role, bool}>
     */
    public static function rolesAndFullNames(): iterable
    {
        yield 'walker' => [Role::Walker, false];
        yield 'analyst' => [Role::Analyst, false];
        yield 'coordinator' => [Role::Coordinator, true];
        yield 'admin' => [Role::Admin, true];
    }

    #[Test]
    public function it_shows_the_round_with_its_start_and_staffing_in_local_time(): void
    {
        $speeltuin = MeetingPoint::create('Speeltuin');
        $speeltuin->placeAt(new Coordinates(52.1, 5.1));
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::UPCOMING), $speeltuin, 'Bij het hek'), $this->jan);
        $directory = new FixedMemberDirectory([FixedMemberDirectory::activeProfile($this->jan)]);

        $output = $this->handler($directory)(new GetRound($round->id()->toRfc4122()));

        $this->assertEquals(
            new GetRoundOutput(
                id: $round->id()->toRfc4122(),
                startsAt: '2026-11-16T22:00:00+01:00',
                daypart: 'evening',
                status: 'seeking_buddy',
                walkerCount: 1,
                minimumWalkers: 2,
                maximumWalkers: 3,
                openSpots: 2,
                meetingPoint: ['id' => $speeltuin->id()->toRfc4122(), 'name' => 'Speeltuin', 'latitude' => 52.1, 'longitude' => 5.1],
                startNote: 'Bij het hek',
                cancelReason: null,
                ownerId: $this->jan->toRfc4122(),
                walkers: [['id' => $this->jan->toRfc4122(), 'displayName' => 'Jan B.', 'name' => 'Jan B. Bakker']],
                inactiveWalkerCount: 0,
                report: null,
                comments: [],
            ),
            $output,
        );
    }

    #[Test]
    public function it_shows_why_a_cancelled_round_was_not_walked(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::PAST)), $this->jan, $this->piet);
        $round->cancel('Loopmaatje kwam niet', new DateTimeImmutable(self::NOW));

        $output = $this->handler()(new GetRound($round->id()->toRfc4122()));

        $this->assertSame(['cancelled', 'Loopmaatje kwam niet'], [$output->status, $output->cancelReason]);
    }

    #[Test]
    public function it_shows_a_meeting_point_without_coordinates(): void
    {
        $speeltuin = MeetingPoint::create('Speeltuin');
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::UPCOMING), $speeltuin), $this->jan);

        $output = $this->handler()(new GetRound($round->id()->toRfc4122()));

        $this->assertSame(
            ['id' => $speeltuin->id()->toRfc4122(), 'name' => 'Speeltuin', 'latitude' => null, 'longitude' => null],
            $output->meetingPoint,
        );
    }

    #[Test]
    public function it_lists_the_walkers_in_the_order_they_joined(): void
    {
        $kees = Uuid::v7();
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::UPCOMING)), $this->jan);
        $round->signUp($this->piet, new DateTimeImmutable('2026-11-03T12:00:00+00:00'));
        $round->signUp($kees, new DateTimeImmutable('2026-11-02T12:00:00+00:00'));
        $directory = new FixedMemberDirectory([
            FixedMemberDirectory::activeProfile($this->piet, 'Piet V.'),
            FixedMemberDirectory::activeProfile($kees, 'Kees D.'),
            FixedMemberDirectory::activeProfile($this->jan),
        ]);

        $output = $this->handler($directory)(new GetRound($round->id()->toRfc4122()));

        $this->assertSame(['Jan B.', 'Kees D.', 'Piet V.'], \array_column($output->walkers, 'displayName'));
    }

    /**
     * Only Membership knows who is inactive; a walker it has no profile for counts as inactive as well.
     */
    #[Test]
    public function it_lists_inactive_walkers_as_a_count(): void
    {
        $unknown = Uuid::v7();
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::PAST)), $this->jan, $this->piet, $unknown);
        $directory = new FixedMemberDirectory([
            FixedMemberDirectory::activeProfile($this->jan),
            new MemberProfile($this->piet, 'Piet V.', 'Piet de Vries', null, active: false),
        ]);

        $output = $this->handler($directory)(new GetRound($round->id()->toRfc4122()));

        $this->assertSame(
            [[['id' => $this->jan->toRfc4122(), 'displayName' => 'Jan B.', 'name' => 'Jan B. Bakker']], 2],
            [$output->walkers, $output->inactiveWalkerCount],
        );
    }

    #[DataProvider('rolesAndFullNames')]
    #[Test]
    public function it_shows_the_full_name_only_to_a_coordinator_or_admin(Role $role, bool $seesFullName): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::UPCOMING)), $this->jan);
        $directory = new FixedMemberDirectory([FixedMemberDirectory::activeProfile($this->jan)]);

        $output = $this->handler($directory, new Actor(Uuid::v7(), [$role]))(new GetRound($round->id()->toRfc4122()));

        $this->assertSame($seesFullName ? ['Jan B. Bakker'] : [], \array_column($output->walkers, 'name'));
    }

    #[Test]
    public function it_shows_the_phone_to_another_walker_of_the_round(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::UPCOMING)), $this->jan, $this->piet);
        $directory = new FixedMemberDirectory([
            new MemberProfile($this->jan, 'Jan B.', 'Jan Bakker', '0612345678', active: true),
            FixedMemberDirectory::activeProfile($this->piet, 'Piet V.'),
        ]);

        $output = $this->handler($directory, new Actor($this->piet, [Role::Walker]))(new GetRound($round->id()->toRfc4122()));

        $this->assertSame(
            [
                ['id' => $this->jan->toRfc4122(), 'displayName' => 'Jan B.', 'phone' => '0612345678'],
                ['id' => $this->piet->toRfc4122(), 'displayName' => 'Piet V.', 'phone' => null],
            ],
            $output->walkers,
        );
    }

    #[Test]
    public function it_shows_the_phone_to_a_coordinator(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::UPCOMING)), $this->jan);
        $directory = new FixedMemberDirectory([new MemberProfile($this->jan, 'Jan B.', 'Jan Bakker', '0612345678', active: true)]);

        $output = $this->handler($directory, new Actor(Uuid::v7(), [Role::Coordinator]))(new GetRound($round->id()->toRfc4122()));

        $this->assertSame(
            [['id' => $this->jan->toRfc4122(), 'displayName' => 'Jan B.', 'name' => 'Jan Bakker', 'phone' => '0612345678']],
            $output->walkers,
        );
    }

    #[Test]
    public function it_hides_the_phone_from_a_member_who_neither_walks_the_round_nor_coordinates(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::UPCOMING)), $this->jan);
        $directory = new FixedMemberDirectory([new MemberProfile($this->jan, 'Jan B.', 'Jan Bakker', '0612345678', active: true)]);

        $output = $this->handler($directory, new Actor(Uuid::v7(), [Role::Walker]))(new GetRound($round->id()->toRfc4122()));

        $this->assertSame([['id' => $this->jan->toRfc4122(), 'displayName' => 'Jan B.']], $output->walkers);
    }

    /**
     * Membership leaves the phone out of the profile of a member who does not consent to sharing it.
     */
    #[Test]
    public function it_hides_the_phone_of_a_walker_who_does_not_consent(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::UPCOMING)), $this->jan, $this->piet);
        $directory = new FixedMemberDirectory([
            FixedMemberDirectory::activeProfile($this->jan),
            FixedMemberDirectory::activeProfile($this->piet, 'Piet V.'),
        ]);

        $output = $this->handler($directory, new Actor($this->piet, [Role::Walker]))(new GetRound($round->id()->toRfc4122()));

        $this->assertSame(['id' => $this->jan->toRfc4122(), 'displayName' => 'Jan B.', 'phone' => null], $output->walkers[0]);
    }

    /**
     * The system acts for nobody in particular, so no policy grants it the phone numbers.
     */
    #[Test]
    public function it_hides_the_phone_from_the_system(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::UPCOMING)), $this->jan);
        $directory = new FixedMemberDirectory([new MemberProfile($this->jan, 'Jan B.', 'Jan Bakker', '0612345678', active: true)]);

        $output = $this->handler($directory)(new GetRound($round->id()->toRfc4122()));

        $this->assertSame([['id' => $this->jan->toRfc4122(), 'displayName' => 'Jan B.', 'name' => 'Jan Bakker']], $output->walkers);
    }

    #[Test]
    public function it_shows_the_report_with_its_outcome_and_author(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::PAST)), $this->jan, $this->piet);
        $report = $round->submitReport(
            $this->piet,
            Outcome::create('Losse stoeptegel', OutcomeKind::Remarks),
            'Bij de speeltuin',
            new DateTimeImmutable(self::NOW),
        );
        $directory = new FixedMemberDirectory([FixedMemberDirectory::activeProfile($this->piet, 'Piet V.')]);

        $output = $this->handler($directory)(new GetRound($round->id()->toRfc4122()));

        $this->assertSame(
            [
                'id' => $report->id()->toRfc4122(),
                'outcome' => 'Losse stoeptegel',
                'outcomeKind' => 'remarks',
                'note' => 'Bij de speeltuin',
                'authorId' => $this->piet->toRfc4122(),
                'authorDisplayName' => 'Piet V.',
                'reportedAt' => '2026-11-15T13:00:00+01:00',
            ],
            $output->report,
        );
    }

    #[Test]
    public function it_shows_the_comments_oldest_first_with_their_authors(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::UPCOMING)), $this->jan);
        $later = RoundComment::post($round, $this->piet, 'Ik neem een zaklamp mee.', new DateTimeImmutable('2026-11-15T10:00:00+00:00'));
        $sooner = RoundComment::post($round, $this->jan, 'Wie loopt er mee?', new DateTimeImmutable('2026-11-14T10:00:00+00:00'));
        $this->comments->add($later);
        $this->comments->add($sooner);
        $directory = new FixedMemberDirectory([
            FixedMemberDirectory::activeProfile($this->jan),
            FixedMemberDirectory::activeProfile($this->piet, 'Piet V.'),
        ]);

        $output = $this->handler($directory)(new GetRound($round->id()->toRfc4122()));

        $this->assertSame(
            [
                [
                    'id' => $sooner->id()->toRfc4122(),
                    'authorId' => $this->jan->toRfc4122(),
                    'authorDisplayName' => 'Jan B.',
                    'text' => 'Wie loopt er mee?',
                    'postedAt' => '2026-11-14T11:00:00+01:00',
                ],
                [
                    'id' => $later->id()->toRfc4122(),
                    'authorId' => $this->piet->toRfc4122(),
                    'authorDisplayName' => 'Piet V.',
                    'text' => 'Ik neem een zaklamp mee.',
                    'postedAt' => '2026-11-15T11:00:00+01:00',
                ],
            ],
            $output->comments,
        );
    }

    /**
     * The frontend shows a null display name as "removed member".
     */
    #[Test]
    public function it_shows_an_erased_author_without_a_display_name(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::PAST)), $this->jan, $this->piet);
        $round->submitReport($this->piet, Outcome::create('Niets bijzonders', OutcomeKind::NothingToReport), null, new DateTimeImmutable(self::NOW));
        $this->comments->add(RoundComment::post($round, $this->piet, 'Rustige avond.', new DateTimeImmutable(self::NOW)));
        $directory = new FixedMemberDirectory([
            FixedMemberDirectory::activeProfile($this->jan),
            new MemberProfile($this->piet, null, null, null, active: false),
        ]);

        $output = $this->handler($directory)(new GetRound($round->id()->toRfc4122()));

        $this->assertNotNull($output->report);
        $this->assertSame([null, null], [$output->report['authorDisplayName'], $output->comments[0]['authorDisplayName']]);
    }

    /**
     * A note may be about a suspect: a member who neither walks the round, coordinates nor analyses sees the outcome
     * only.
     */
    #[Test]
    public function it_hides_the_note_and_comments_from_a_member_who_does_not_walk_the_round(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::PAST)), $this->jan, $this->piet);
        $round->submitReport($this->piet, Outcome::create('Incident', OutcomeKind::Incident), 'Bij de speeltuin', new DateTimeImmutable(self::NOW));
        $this->comments->add(RoundComment::post($round, $this->piet, 'Politie gebeld.', new DateTimeImmutable(self::NOW)));

        $output = $this->handler(actor: new Actor(Uuid::v7(), [Role::Walker, Role::Admin]))(new GetRound($round->id()->toRfc4122()));

        $this->assertNotNull($output->report);
        $this->assertSame(['Incident', null, []], [$output->report['outcome'], $output->report['note'], $output->comments]);
    }

    #[Test]
    public function it_shows_the_note_and_comments_to_a_walker_of_the_round(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::PAST)), $this->jan, $this->piet);
        $round->submitReport($this->piet, Outcome::create('Incident', OutcomeKind::Incident), 'Bij de speeltuin', new DateTimeImmutable(self::NOW));
        $this->comments->add(RoundComment::post($round, $this->piet, 'Politie gebeld.', new DateTimeImmutable(self::NOW)));

        $output = $this->handler(actor: new Actor($this->jan, [Role::Walker]))(new GetRound($round->id()->toRfc4122()));

        $this->assertNotNull($output->report);
        $this->assertSame(['Bij de speeltuin', ['Politie gebeld.']], [$output->report['note'], \array_column($output->comments, 'text')]);
    }

    #[Test]
    public function it_refuses_an_unknown_round(): void
    {
        $this->expectException(NotFound::class);

        $this->handler()(new GetRound(Uuid::v7()->toRfc4122()));
    }

    #[Test]
    public function it_returns_nothing_when_access_is_denied(): void
    {
        $round = $this->round(new RoundStart(new DateTimeImmutable(self::UPCOMING)), $this->jan);
        $authorization = FakeAuthorization::refusing();

        $output = null;
        $refusal = null;
        try {
            $output = $this->handler(authorization: $authorization)(new GetRound($round->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertNull($output);
        $this->assertSame([['round.view', $round]], $authorization->asked);
    }

    private function round(RoundStart $start, Uuid $firstWalker, Uuid ...$others): Round
    {
        $round = Round::plan($start, $firstWalker, new StaffingRule(minimumWalkers: 2, maximumWalkers: 3), new DateTimeImmutable(self::CREATED_AT));
        foreach ($others as $walker) {
            $round->signUp($walker, new DateTimeImmutable(self::CREATED_AT));
        }
        $this->rounds->add($round);

        return $round;
    }

    private function handler(
        FixedMemberDirectory $directory = new FixedMemberDirectory(),
        ?Actor $actor = null,
        ?FakeAuthorization $authorization = null,
    ): GetRoundHandler {
        return new GetRoundHandler(
            $this->rounds,
            $this->comments,
            $directory,
            new RoundPolicy(),
            new ReportReadingPolicy(),
            $authorization ?? FakeAuthorization::granting(),
            new FixedExecutionContext($actor),
            new MockClock(self::NOW),
        );
    }
}
