<?php

declare(strict_types=1);

namespace App\Tests\Audit\UseCase\VerifyAuditChain;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\ChainLink;
use App\Audit\Infrastructure\HmacChainHasher;
use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChain;
use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChainHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Audit\Fake\TamperedEntry;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class VerifyAuditChainHandlerTest extends TestCase
{
    private RecordingAuditEntryRepository $entries;
    private HmacChainHasher $hasher;

    #[Override]
    protected function setUp(): void
    {
        $this->entries = new RecordingAuditEntryRepository();
        $this->hasher = new HmacChainHasher('audit-key');
    }

    #[Test]
    public function it_reports_an_intact_chain_through_the_last_entry_on_the_local_clock(): void
    {
        $this->appended(3);

        $output = $this->handler()(new VerifyAuditChain());

        $this->assertSame(
            [true, 3, '2026-11-15T13:03:00+01:00', 3, 3, null, null],
            [
                $output->intact,
                $output->intactThrough,
                $output->intactThroughAt,
                $output->checked,
                $output->lastPosition,
                $output->breakReason,
                $output->breakPosition,
            ],
        );
    }

    #[Test]
    public function it_reports_the_first_entry_that_does_not_hold_and_why(): void
    {
        $entries = $this->appended(3);
        $this->entries->replace($entries[1], TamperedEntry::of($entries[1], ['action' => 'round.deleted']));

        $output = $this->handler()(new VerifyAuditChain());

        $this->assertSame(
            [false, 1, 'changed_content', 2],
            [$output->intact, $output->intactThrough, $output->breakReason, $output->breakPosition],
        );
    }

    /**
     * The chain is read 500 entries at a time; an entry whose hash does not fit is judged by the one after it, which
     * may be the first of the next batch.
     */
    #[Test]
    public function it_judges_the_last_entry_of_a_batch_by_the_first_entry_of_the_next(): void
    {
        $entries = $this->appended(501);
        $this->entries->replace($entries[499], TamperedEntry::of($entries[499], ['action' => 'round.deleted']));

        $output = $this->handler()(new VerifyAuditChain());

        $this->assertSame(['changed_content', 500], [$output->breakReason, $output->breakPosition]);
    }

    #[Test]
    public function it_checks_every_batch_of_a_long_chain(): void
    {
        $this->appended(1001);

        $output = $this->handler()(new VerifyAuditChain());

        $this->assertSame([true, 1001, 1001], [$output->intact, $output->intactThrough, $output->checked]);
    }

    /**
     * Entries stored before the chain existed have a position but no hash; they cannot be checked and are counted
     * apart.
     */
    #[Test]
    public function it_counts_the_entries_from_before_the_chain_apart(): void
    {
        foreach ([1, 2] as $position) {
            $this->entries->add(TamperedEntry::of($this->entry(1), ['chainPosition' => $position]));
        }
        $this->entries->startChainAt(new ChainLink(2, \str_repeat("\0", 32)));
        $this->appended(1);

        $output = $this->handler()(new VerifyAuditChain());

        $this->assertSame([true, 2, 1, 3], [$output->intact, $output->beforeChain, $output->checked, $output->intactThrough]);
    }

    #[Test]
    public function it_counts_the_entries_whose_changes_were_erased(): void
    {
        $entries = $this->appended(2);
        $entries[0]->eraseChanges();

        $output = $this->handler()(new VerifyAuditChain());

        $this->assertSame([true, 1], [$output->intact, $output->erased]);
    }

    /**
     * Pruning removes the oldest entries and keeps the hash of the last one, so the check starts there.
     */
    #[Test]
    public function it_reports_an_intact_chain_after_pruning(): void
    {
        $this->appended(3);
        $this->entries->pruneThrough(2);

        $output = $this->handler()(new VerifyAuditChain());

        $this->assertSame([true, 3, 1], [$output->intact, $output->intactThrough, $output->checked]);
    }

    #[Test]
    public function it_checks_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new VerifyAuditChain());
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['audit.verify', null]], $authorization->asked);
    }

    /**
     * @return list<AuditEntry> entries linked after the head and appended, a minute apart
     */
    private function appended(int $count): array
    {
        $entries = [];
        for ($minute = 1; $minute <= $count; ++$minute) {
            $entry = $this->entry($minute);
            $entry->link($this->entries->lockHead(), $this->hasher);
            $this->entries->append($entry);
            $entries[] = $entry;
        }

        return $entries;
    }

    private function entry(int $minute): AuditEntry
    {
        return AuditEntry::of(
            new AuditedAction('round.changed', 'round', Uuid::v7(), ['startsAt' => ['old' => '22:00', 'new' => '23:00']]),
            Uuid::v7(),
            'Anna H.',
            Channel::Web,
            new DateTimeImmutable('2026-11-15T12:00:00+00:00')->modify(\sprintf('+%d minutes', $minute)),
        );
    }

    private function handler(?FakeAuthorization $authorization = null): VerifyAuditChainHandler
    {
        return new VerifyAuditChainHandler($this->entries, $this->hasher, $authorization ?? FakeAuthorization::granting(), new ImmediateTransaction());
    }
}
