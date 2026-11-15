<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\CreateRound;

use App\Patrol\Domain\BuddyCaller;
use App\Patrol\Domain\MeetingPoint;
use App\Patrol\Domain\MeetingPointArchived;
use App\Patrol\Domain\MemberInactive;
use App\Patrol\Domain\StaffingRule;
use App\Patrol\UseCase\CreateRound\CreateRound;
use App\Patrol\UseCase\CreateRound\CreateRoundHandler;
use App\Settings\UseCase\GetStaffingDefaults\GetStaffingDefaultsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\MemberProfile;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Patrol\Fake\InMemoryRoundCommentRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use App\Tests\Shared\Fake\FixedMemberDirectory;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Shared\Fake\RecordingNotifier;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * The clock reads 15 November 2026 at 13:00 local time; rounds start on 20 November at 22:00 local time, so their
 * buddy call is due five days ahead and not at creation.
 */
final class CreateRoundHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';
    private const string STARTS_AT = '2026-11-20T22:00:00+01:00';

    private InMemoryRoundRepository $rounds;
    private InMemoryMeetingPointRepository $meetingPoints;
    private InMemoryRoundCommentRepository $comments;
    private RecordingAuditTrail $auditTrail;
    private RecordingNotifier $notifier;
    private Uuid $jan;
    private Uuid $ria;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->meetingPoints = new InMemoryMeetingPointRepository();
        $this->comments = new InMemoryRoundCommentRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->notifier = new RecordingNotifier();
        $this->jan = Uuid::fromString('0193a3c0-0000-7000-8000-00000000000a');
        $this->ria = Uuid::fromString('0193a3c0-0000-7000-8000-00000000000b');
    }

    #[Test]
    public function it_plans_a_round_with_the_actor_as_first_walker_and_the_default_staffing_rule(): void
    {
        $output = $this->handler()(new CreateRound(self::STARTS_AT));

        $round = $this->rounds->get(Uuid::fromString($output->id));
        $this->assertSame('2026-11-20T21:00:00+00:00', $round->startsAt()->format(\DATE_ATOM));
        $this->assertEquals([$this->jan], $round->walkerIds());
        $this->assertNull($round->participationOf($this->jan)?->onBehalfOf());
        $this->assertEquals(new StaffingRule(minimumWalkers: 2, maximumWalkers: 7), $round->staffingRule());
    }

    #[Test]
    public function it_asks_whether_the_actor_may_create_a_round_for_the_first_walker(): void
    {
        $authorization = FakeAuthorization::granting();

        $this->handler(authorization: $authorization)(new CreateRound(self::STARTS_AT));

        $this->assertEquals([['round.create', $this->jan]], $authorization->asked);
    }

    #[Test]
    public function it_plans_a_round_on_behalf_of_another_member_who_owns_it(): void
    {
        $output = $this->handler(actor: new Actor($this->ria, [Role::Coordinator]))(new CreateRound(self::STARTS_AT, firstWalkerId: $this->jan->toRfc4122()));

        $round = $this->rounds->get(Uuid::fromString($output->id));
        $this->assertEquals([$this->jan, $this->ria], [$round->ownerId(), $round->participationOf($this->jan)?->onBehalfOf()]);
    }

    #[Test]
    public function it_refuses_a_first_walker_who_is_not_active(): void
    {
        $directory = new FixedMemberDirectory([
            FixedMemberDirectory::activeProfile($this->ria),
            new MemberProfile($this->jan, 'Jan B.', 'Jan Bakker', null, active: false),
        ]);

        $refusal = null;
        try {
            $this->handler(actor: new Actor($this->ria, [Role::Coordinator]), directory: $directory)(
                new CreateRound(self::STARTS_AT, firstWalkerId: $this->jan->toRfc4122()),
            );
        } catch (MemberInactive $exception) {
            $refusal = $exception;
        }

        $this->assertEquals(MemberInactive::forMember($this->jan), $refusal);
        $this->assertSame([[], []], [$this->rounds->all(), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_starts_from_a_meeting_point_with_a_note(): void
    {
        $meetingPoint = MeetingPoint::create('Speeltuin');
        $this->meetingPoints->add($meetingPoint);

        $output = $this->handler()(new CreateRound(self::STARTS_AT, meetingPointId: $meetingPoint->id()->toRfc4122(), startNote: 'Bij het hek'));

        $round = $this->rounds->get(Uuid::fromString($output->id));
        $this->assertSame([$meetingPoint, 'Bij het hek'], [$round->meetingPoint(), $round->startNote()]);
    }

    #[Test]
    public function it_refuses_an_archived_meeting_point(): void
    {
        $meetingPoint = MeetingPoint::create('Speeltuin');
        $meetingPoint->archive();
        $this->meetingPoints->add($meetingPoint);

        $this->expectExceptionObject(MeetingPointArchived::forItem($meetingPoint->id()));

        $this->handler()(new CreateRound(self::STARTS_AT, meetingPointId: $meetingPoint->id()->toRfc4122()));
    }

    #[Test]
    public function it_records_who_created_the_round_and_for_whom(): void
    {
        $output = $this->handler(actor: new Actor($this->ria, [Role::Coordinator]))(new CreateRound(self::STARTS_AT, firstWalkerId: $this->jan->toRfc4122()));

        $this->assertEquals(
            [
                new AuditedAction('round.created', 'round', Uuid::fromString($output->id), [
                    'startsAt' => ['old' => null, 'new' => '2026-11-20T21:00:00+00:00'],
                    'firstWalker' => ['old' => null, 'new' => $this->jan->toRfc4122()],
                ]),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_posts_the_comment_of_the_creator_on_the_round(): void
    {
        $output = $this->handler()(new CreateRound(self::STARTS_AT, comment: 'Wie loopt er mee?'));

        $comments = $this->comments->forRound($this->rounds->get(Uuid::fromString($output->id)));
        $this->assertSame([[$this->jan->toRfc4122(), 'Wie loopt er mee?']], \array_map(
            static fn(\App\Patrol\Domain\RoundComment $comment): array => [$comment->authorId()->toRfc4122(), $comment->text()],
            $comments,
        ));
        $this->assertSame(
            ['round.created', 'comment.added'],
            \array_map(static fn(AuditedAction $action): string => $action->action, $this->auditTrail->recorded),
        );
    }

    #[Test]
    public function it_calls_for_a_buddy_at_once_for_a_round_less_than_a_day_ahead(): void
    {
        $piet = Uuid::v7();

        $output = $this->handler(directory: $this->directory(subscribers: [$this->jan, $piet]))(new CreateRound('2026-11-16T09:00:00+01:00'));

        $roundId = Uuid::fromString($output->id);
        $this->assertEquals([new Notification($piet, 'round.buddy_needed', ['roundId' => $roundId->toRfc4122()])], $this->notifier->sent);
        $this->assertEquals(
            new AuditedAction('round.buddy_call_sent', 'round', $roundId, ['recipients' => ['old' => null, 'new' => 1]]),
            $this->auditTrail->recorded[1] ?? null,
        );
    }

    /**
     * Walkers first get time to sign up by themselves; the scheduler sends the call when it falls due.
     */
    #[Test]
    public function it_leaves_the_buddy_call_of_a_round_days_ahead_to_the_scheduler(): void
    {
        $this->handler(directory: $this->directory(subscribers: [Uuid::v7()]))(new CreateRound(self::STARTS_AT));

        $this->assertSame([], $this->notifier->sent);
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler(authorization: $authorization)(new CreateRound(self::STARTS_AT, comment: 'Wie loopt er mee?'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['round.create', $this->jan]], $authorization->asked);
        $this->assertSame([[], [], []], [$this->rounds->all(), $this->auditTrail->recorded, $this->notifier->sent]);
    }

    /**
     * @param list<Uuid> $subscribers
     */
    private function directory(array $subscribers = []): FixedMemberDirectory
    {
        return new FixedMemberDirectory(
            [FixedMemberDirectory::activeProfile($this->jan), FixedMemberDirectory::activeProfile($this->ria, 'Ria S.')],
            $subscribers,
        );
    }

    private function handler(
        ?Actor $actor = null,
        ?FakeAuthorization $authorization = null,
        ?FixedMemberDirectory $directory = null,
    ): CreateRoundHandler {
        $directory ??= $this->directory();

        return new CreateRoundHandler(
            $this->rounds,
            $this->meetingPoints,
            $this->comments,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
            new FixedExecutionContext($actor ?? new Actor($this->jan, [Role::Walker])),
            $directory,
            new BuddyCaller($directory, $this->notifier),
            new GetStaffingDefaultsHandler(new InMemorySettingRepository()),
        );
    }
}
