<?php

declare(strict_types=1);

namespace App\Tests\Audit\UseCase\GetRoundHistory;

use App\Audit\Domain\AuditEntry;
use App\Audit\UseCase\GetRoundHistory\GetRoundHistory;
use App\Audit\UseCase\GetRoundHistory\GetRoundHistoryHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Shared\Domain\NotFound;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GetRoundHistoryHandlerTest extends TestCase
{
    private RecordingAuditEntryRepository $entries;
    private Uuid $round;
    private Uuid $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->entries = new RecordingAuditEntryRepository();
        $this->round = Uuid::v7();
        $this->jan = Uuid::v7();
    }

    #[Test]
    public function it_gives_the_history_of_the_round_oldest_first_in_local_time(): void
    {
        $this->stored(new AuditedAction('participation.signed_up', 'round', $this->round, ['walker' => [
            'old' => null,
            'new' => 'w',
        ]]), '2026-11-14T12:00:00+00:00');
        $this->stored(new AuditedAction('round.created', 'round', $this->round), '2026-11-13T12:00:00+00:00');
        $this->stored(new AuditedAction('round.created', 'round', Uuid::v7()), '2026-11-13T13:00:00+00:00');

        $output = $this->handler()(new GetRoundHistory($this->round->toRfc4122()));

        $this->assertSame(
            [
                [
                    'occurredAt' => '2026-11-13T13:00:00+01:00',
                    'action' => 'round.created',
                    'actor' => 'member',
                    'actorId' => $this->jan->toRfc4122(),
                    'actorName' => 'Jan B.',
                    'channel' => 'web',
                    'changes' => [],
                ],
                [
                    'occurredAt' => '2026-11-14T13:00:00+01:00',
                    'action' => 'participation.signed_up',
                    'actor' => 'member',
                    'actorId' => $this->jan->toRfc4122(),
                    'actorName' => 'Jan B.',
                    'channel' => 'web',
                    'changes' => ['walker' => ['old' => null, 'new' => 'w']],
                ],
            ],
            $output->entries,
        );
    }

    /**
     * The frontend shows "removed member" for an erased member and "system" for the app itself.
     */
    #[Test]
    public function it_tells_an_erased_member_and_the_system_apart(): void
    {
        $erased = $this->stored(new AuditedAction('participation.withdrawn', 'round', $this->round), '2026-11-14T12:00:00+00:00');
        $erased->eraseActorName();
        $this->entries->add(AuditEntry::of(
            new AuditedAction('round.buddy_call_sent', 'round', $this->round),
            null,
            null,
            Channel::Console,
            new DateTimeImmutable('2026-11-14T13:00:00+00:00'),
        ));

        $output = $this->handler()(new GetRoundHistory($this->round->toRfc4122()));

        $this->assertSame(
            [['removed_member', $this->jan->toRfc4122(), null], ['system', null, null]],
            \array_map(static fn(array $entry): array => [$entry['actor'], $entry['actorId'], $entry['actorName']], $output->entries),
        );
    }

    #[Test]
    public function it_answers_a_round_without_history_as_not_found(): void
    {
        $this->expectException(NotFound::class);

        $this->handler()(new GetRoundHistory($this->round->toRfc4122()));
    }

    #[Test]
    public function it_refuses_a_member_who_may_not_view_the_history(): void
    {
        $this->stored(new AuditedAction('round.created', 'round', $this->round), '2026-11-13T12:00:00+00:00');
        $authorization = FakeAuthorization::refusing();

        try {
            $this->handler($authorization)(new GetRoundHistory($this->round->toRfc4122()));
            $this->fail('The history was given.');
        } catch (AccessDenied) {
            $this->assertSame([['audit.view_round_history', null]], $authorization->asked);
        }
    }

    private function stored(AuditedAction $action, string $at): AuditEntry
    {
        $entry = AuditEntry::of($action, $this->jan, 'Jan B.', Channel::Web, new DateTimeImmutable($at));
        $this->entries->add($entry);

        return $entry;
    }

    private function handler(?FakeAuthorization $authorization = null): GetRoundHistoryHandler
    {
        return new GetRoundHistoryHandler($this->entries, $authorization ?? FakeAuthorization::granting());
    }
}
