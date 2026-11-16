<?php

declare(strict_types=1);

namespace App\Tests\Audit\Infrastructure\Console;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\ChainLink;
use App\Audit\Infrastructure\Console\VerifyAuditChainCommand;
use App\Audit\Infrastructure\HmacChainHasher;
use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChainHandler;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Shared\Infrastructure\Security\RunAs;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Audit\Fake\TamperedEntry;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Uid\Uuid;

final class VerifyAuditChainCommandTest extends TestCase
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
    public function it_reports_how_far_the_chain_holds_and_the_position_to_note_down(): void
    {
        $this->appended(2);
        $tester = $this->tester();

        $exitCode = $tester->execute([]);

        $display = $this->display($tester);
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('The audit chain is intact through entry 2 (2026-11-15T13:02:00+01:00).', $display);
        $this->assertStringContainsString('Last position: 2.', $display);
    }

    #[Test]
    public function it_fails_with_the_first_entry_that_does_not_hold_and_the_reason(): void
    {
        $entries = $this->appended(3);
        $this->entries->replace($entries[1], null);
        $tester = $this->tester();

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(
            'The audit chain breaks at entry 2: no entry holds this position. It is intact through entry 1 (2026-11-15T13:01:00+01:00).',
            $this->display($tester),
        );
    }

    #[Test]
    public function it_names_the_entries_it_could_not_check(): void
    {
        $this->entries->add(TamperedEntry::of($this->entry(0), ['chainPosition' => 1]));
        $this->entries->startChainAt(new ChainLink(1, \str_repeat("\0", 32)));
        $entries = $this->appended(1);
        $entries[0]->eraseChanges();
        $tester = $this->tester();

        $tester->execute([]);

        $display = $this->display($tester);
        $this->assertStringContainsString('1 entries come from before the chain and are not checked.', $display);
        $this->assertStringContainsString('1 entries had their changed fields erased with a member', $display);
    }

    /**
     * @return list<AuditEntry>
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
            new AuditedAction('member.added', 'member', Uuid::v7(), ['displayName' => ['old' => null, 'new' => 'Anna H.']]),
            null,
            null,
            Channel::Console,
            new DateTimeImmutable('2026-11-15T12:00:00+00:00')->modify(\sprintf('+%d minutes', $minute)),
        );
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new VerifyAuditChainCommand(
            new VerifyAuditChainHandler($this->entries, $this->hasher, FakeAuthorization::granting(), new ImmediateTransaction()),
            new RunAs(new TokenStorage(), new MockClock()),
        ));
    }

    /**
     * SymfonyStyle wraps its blocks at the terminal width; one line per sentence keeps the assertions readable.
     */
    private function display(CommandTester $tester): string
    {
        return (string) \preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
