<?php

declare(strict_types=1);

namespace App\Tests\Audit\Infrastructure;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\ChainLink;
use App\Audit\Domain\ChainVerifier;
use App\Audit\Infrastructure\ChainedAuditTrail;
use App\Audit\Infrastructure\HmacChainHasher;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Shared\Fake\FixedExecutionContext;
use App\Tests\Shared\Fake\FixedMemberNames;
use App\Tests\Shared\Fake\ImmediateTransaction;
use DateTimeImmutable;
use DomainException;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class ChainedAuditTrailTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    private RecordingAuditEntryRepository $entries;
    private ImmediateTransaction $transaction;
    private HmacChainHasher $hasher;

    #[Override]
    protected function setUp(): void
    {
        $this->entries = new RecordingAuditEntryRepository();
        $this->transaction = new ImmediateTransaction();
        $this->hasher = new HmacChainHasher('audit-key');
    }

    #[Test]
    public function it_records_the_action_with_the_time_the_actor_by_name_and_the_channel(): void
    {
        $anna = Uuid::v7();
        $round = Uuid::v7();
        $trail = $this->trail(new Actor($anna, [Role::Walker]), Channel::Api, [$anna->toRfc4122() => 'Anna H.']);

        $this->transaction->run(static fn() => $trail->record(
            new AuditedAction('round.changed', 'round', $round, ['startsAt' => ['old' => '22:00', 'new' => '23:00']]),
        ));

        [$entry] = $this->entries->all();
        $this->assertEquals(
            [new DateTimeImmutable(self::NOW), $anna, 'Anna H.', 'round.changed', $round, Channel::Api, ['startsAt' => ['old' => '22:00', 'new' => '23:00']]],
            [$entry->occurredAt(), $entry->actorId(), $entry->actorName(), $entry->action(), $entry->subjectId(), $entry->channel(), $entry->changes()],
        );
    }

    #[Test]
    public function it_records_the_system_as_actor_when_no_member_acts(): void
    {
        $trail = $this->trail(null, Channel::Cron);

        $this->transaction->run(static fn() => $trail->record(new AuditedAction('participation.reminded', 'round', Uuid::v7())));

        [$entry] = $this->entries->all();
        $this->assertSame([true, null], [$entry->isBySystem(), $entry->actorName()]);
    }

    /**
     * The head is locked as the last step: a transaction that held it while it waited for the lock on a round could
     * deadlock with one that holds the round and waits for the head.
     */
    #[Test]
    public function it_links_the_entry_only_as_the_last_step_of_the_transaction(): void
    {
        $trail = $this->trail(null, Channel::Console);
        $seen = [];

        $this->transaction->run(function () use ($trail, &$seen): void {
            $trail->record(new AuditedAction('round.created', 'round', Uuid::v7()));
            $seen[] = \count($this->entries->all());
        });
        $seen[] = \count($this->entries->all());

        $this->assertSame([0, 1], $seen);
    }

    #[Test]
    public function it_links_each_entry_after_the_head_so_the_chain_holds(): void
    {
        $head = new ChainLink(41, \str_repeat('h', 32));
        $this->entries->startChainAt($head);
        $trail = $this->trail(null, Channel::Console);

        $this->transaction->run(static function () use ($trail): void {
            $trail->record(new AuditedAction('round.created', 'round', Uuid::v7()));
            $trail->record(new AuditedAction('participation.signed_up', 'round', Uuid::v7()));
        });
        $this->transaction->run(static fn() => $trail->record(new AuditedAction('round.deleted', 'round', Uuid::v7())));

        $verifier = new ChainVerifier($this->hasher, $head);
        \array_map($verifier->add(...), $this->entries->all());
        $report = $verifier->finish($this->entries->head());
        $this->assertSame(
            [[42, 43, 44], null],
            [\array_map(static fn(AuditEntry $entry): ?int => $entry->chainPosition(), $this->entries->all()), $report->break],
        );
    }

    #[Test]
    public function it_leaves_no_entry_and_no_position_taken_when_the_transaction_fails(): void
    {
        $trail = $this->trail(null, Channel::Console);
        $refusal = new DomainException('The round is full.');

        $caught = null;
        try {
            $this->transaction->run(static function () use ($trail, $refusal): never {
                $trail->record(new AuditedAction('participation.signed_up', 'round', Uuid::v7()));

                throw $refusal;
            });
        } catch (DomainException $exception) {
            $caught = $exception;
        }

        $this->assertSame([$refusal, [], 0], [$caught, $this->entries->all(), $this->entries->head()->position]);
    }

    /**
     * An entry outside a transaction would take the head without a lock that lasts until the commit.
     */
    #[Test]
    public function it_refuses_to_record_outside_a_transaction(): void
    {
        $trail = $this->trail(null, Channel::Console);

        $this->expectException(LogicException::class);

        $trail->record(new AuditedAction('round.created', 'round', Uuid::v7()));
    }

    /**
     * @param array<string, string> $names
     */
    private function trail(?Actor $actor, Channel $channel, array $names = []): ChainedAuditTrail
    {
        return new ChainedAuditTrail(
            $this->entries,
            $this->transaction,
            $this->hasher,
            new MockClock(self::NOW),
            new FixedExecutionContext($actor, $channel),
            new FixedMemberNames($names),
        );
    }
}
