<?php

declare(strict_types=1);

namespace App\Tests\Audit\UseCase\ListAuditEntries;

use App\Audit\Domain\AuditEntry;
use App\Audit\UseCase\ListAuditEntries\ListAuditEntries;
use App\Audit\UseCase\ListAuditEntries\ListAuditEntriesHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ListAuditEntriesHandlerTest extends TestCase
{
    private RecordingAuditEntryRepository $entries;
    private Uuid $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->entries = new RecordingAuditEntryRepository();
        $this->jan = Uuid::v7();
    }

    #[Test]
    public function it_lists_the_entries_newest_first_in_local_time(): void
    {
        $member = Uuid::v7();
        $this->stored(new AuditedAction('member.roles_changed', 'member', $member, ['roles' => [
            'old' => 'walker',
            'new' => 'walker,coordinator',
        ]]), '2026-11-14T12:00:00+00:00');
        $this->stored(new AuditedAction('member.added', 'member', $member), '2026-11-13T12:00:00+00:00');

        $output = $this->handler()(new ListAuditEntries());

        $this->assertSame(
            [
                'occurredAt' => '2026-11-14T13:00:00+01:00',
                'action' => 'member.roles_changed',
                'actor' => 'member',
                'actorId' => $this->jan->toRfc4122(),
                'actorName' => 'Jan B.',
                'subjectType' => 'member',
                'subjectId' => $member->toRfc4122(),
                'channel' => 'web',
                'changes' => ['roles' => ['old' => 'walker', 'new' => 'walker,coordinator']],
            ],
            $output->entries[0] ?? null,
        );
        $this->assertSame(['member.roles_changed', 'member.added'], \array_column($output->entries, 'action'));
    }

    #[Test]
    public function it_narrows_the_log_to_one_member_action_and_period(): void
    {
        $this->stored(new AuditedAction('round.created', 'round', Uuid::v7()), '2026-11-14T12:00:00+00:00');
        $this->stored(new AuditedAction('round.deleted', 'round', Uuid::v7()), '2026-11-14T12:00:00+00:00');
        $this->stored(new AuditedAction('round.created', 'round', Uuid::v7()), '2026-11-10T12:00:00+00:00');
        $this->entries->add(AuditEntry::of(
            new AuditedAction('round.created', 'round', Uuid::v7()),
            Uuid::v7(),
            'Piet V.',
            Channel::Web,
            new DateTimeImmutable('2026-11-14T12:00:00+00:00'),
        ));

        $output = $this->handler()(new ListAuditEntries(
            member: $this->jan->toRfc4122(),
            action: 'round.created',
            from: '2026-11-13T00:00:00+01:00',
            to: '2026-11-15T00:00:00+01:00',
        ));

        $this->assertSame([['round.created', '2026-11-14T13:00:00+01:00']], \array_map(
            static fn(array $entry): array => [$entry['action'], $entry['occurredAt']],
            $output->entries,
        ));
    }

    #[Test]
    public function it_gives_a_page_of_fifty_with_the_number_of_the_next(): void
    {
        for ($minute = 0; $minute < 51; ++$minute) {
            $this->stored(new AuditedAction('round.created', 'round', Uuid::v7()), \sprintf('2026-11-14T12:%02d:00+00:00', $minute));
        }

        $first = $this->handler()(new ListAuditEntries());
        $second = $this->handler()(new ListAuditEntries(page: 2));

        $this->assertSame([50, 2, 1, null], [\count($first->entries), $first->nextPage, \count($second->entries), $second->nextPage]);
    }

    /**
     * The frontend shows "removed member" for an erased member and "system" for the app itself.
     */
    #[Test]
    public function it_tells_an_erased_member_and_the_system_apart(): void
    {
        $this->stored(new AuditedAction('round.created', 'round', Uuid::v7()), '2026-11-14T12:00:00+00:00')->eraseActorName();
        $this->entries->add(AuditEntry::of(
            new AuditedAction('round.buddy_call_sent', 'round', Uuid::v7()),
            null,
            null,
            Channel::Console,
            new DateTimeImmutable('2026-11-13T12:00:00+00:00'),
        ));

        $output = $this->handler()(new ListAuditEntries());

        $this->assertSame([
            ['removed_member', null],
            ['system',         null],
        ], \array_map(
            static fn(array $entry): array => [$entry['actor'], $entry['actorName']],
            $output->entries,
        ));
    }

    #[Test]
    public function it_gives_every_matching_entry_for_the_csv_file(): void
    {
        for ($minute = 0; $minute < 51; ++$minute) {
            $this->stored(new AuditedAction('round.created', 'round', Uuid::v7()), \sprintf('2026-11-14T12:%02d:00+00:00', $minute));
        }

        $output = $this->handler()(new ListAuditEntries(format: 'csv'));

        $this->assertSame([51, null], [\count($output->entries), $output->nextPage]);
    }

    #[Test]
    public function it_refuses_whoever_may_not_view_the_log(): void
    {
        $authorization = FakeAuthorization::refusing();

        try {
            $this->handler($authorization)(new ListAuditEntries());
            $this->fail('The log was given.');
        } catch (AccessDenied) {
            $this->assertSame([['audit.view', null]], $authorization->asked);
        }
    }

    private function stored(AuditedAction $action, string $at): AuditEntry
    {
        $entry = AuditEntry::of($action, $this->jan, 'Jan B.', Channel::Web, new DateTimeImmutable($at));
        $this->entries->add($entry);

        return $entry;
    }

    private function handler(?FakeAuthorization $authorization = null): ListAuditEntriesHandler
    {
        return new ListAuditEntriesHandler($this->entries, $authorization ?? FakeAuthorization::granting());
    }
}
