<?php

declare(strict_types=1);

namespace App\Tests\Audit\UseCase\ListOwnActivity;

use App\Audit\Domain\AuditEntry;
use App\Audit\UseCase\ListOwnActivity\ListOwnActivity;
use App\Audit\UseCase\ListOwnActivity\ListOwnActivityHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Shared\Fake\FixedExecutionContext;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ListOwnActivityHandlerTest extends TestCase
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
    public function it_lists_what_the_member_did_in_local_time_without_what_others_did(): void
    {
        $round = Uuid::v7();
        $this->entry($this->jan, new AuditedAction('round.walker_joined', 'round', $round), '2026-11-15T21:00:00+00:00');
        $this->entry(Uuid::v7(), new AuditedAction('round.created', 'round', Uuid::v7()), '2026-11-15T22:00:00+00:00');

        $output = $this->handler()(new ListOwnActivity());

        $this->assertSame(
            [[
                'occurredAt' => '2026-11-15T22:00:00+01:00',
                'action' => 'round.walker_joined',
                'subjectType' => 'round',
                'subjectId' => $round->toRfc4122(),
                'channel' => 'web',
                'changes' => [],
            ]],
            $output->entries,
        );
    }

    #[Test]
    public function it_leaves_out_who_another_member_is_and_what_changed_for_them(): void
    {
        $this->entry($this->jan, new AuditedAction('member.roles_changed', 'member', Uuid::v7(), ['roles' => ['old' => 'walker', 'new' => 'walker,admin']]));

        $output = $this->handler()(new ListOwnActivity());

        $line = $output->entries[0] ?? null;
        $this->assertNotNull($line);
        $this->assertSame(['member.roles_changed', null, []], [$line['action'], $line['subjectId'], $line['changes']]);
    }

    #[Test]
    public function it_shows_what_changed_for_the_member_themselves(): void
    {
        $this->entry($this->jan, new AuditedAction('member.consent_changed', 'member', $this->jan, ['consentContactShare' => ['old' => false, 'new' => true]]));

        $output = $this->handler()(new ListOwnActivity());

        $this->assertSame(['consentContactShare' => ['old' => false, 'new' => true]], $output->entries[0]['changes'] ?? null);
    }

    #[Test]
    public function it_gives_a_full_page_with_the_number_of_the_next_one(): void
    {
        for ($i = 0; $i <= ListOwnActivityHandler::PAGE_SIZE; ++$i) {
            $this->entry($this->jan, new AuditedAction('round.created', 'round', Uuid::v7()), \sprintf('2026-11-15T12:00:%02d+00:00', $i));
        }

        $first = $this->handler()(new ListOwnActivity());
        $second = $this->handler()(new ListOwnActivity(page: 2));

        $this->assertSame([50, 2, 1, null], [\count($first->entries), $first->nextPage, \count($second->entries), $second->nextPage]);
    }

    #[Test]
    public function it_refuses_when_nobody_acts(): void
    {
        $this->expectException(AccessDenied::class);

        new ListOwnActivityHandler($this->entries, new FixedExecutionContext())(new ListOwnActivity());
    }

    private function entry(Uuid $actor, AuditedAction $action, string $at = '2026-11-15T12:00:00+00:00'): void
    {
        $this->entries->add(AuditEntry::of($action, $actor, 'Jan B.', Channel::Web, new DateTimeImmutable($at)));
    }

    private function handler(): ListOwnActivityHandler
    {
        return new ListOwnActivityHandler($this->entries, new FixedExecutionContext(new Actor($this->jan, [])));
    }
}
