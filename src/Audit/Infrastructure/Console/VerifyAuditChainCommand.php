<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Console;

use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChain;
use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChainHandler;
use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChainOutput;
use App\Shared\Domain\Channel;
use App\Shared\Infrastructure\Security\RunAs;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Exits with 1 when the chain breaks, so a cron job or monitor can alert on it.
 */
#[AsCommand(name: 'app:audit:verify', description: 'Checks that no entry in the audit log was changed, removed or inserted')]
final class VerifyAuditChainCommand extends Command
{
    private const array REASONS = [
        'changed_content' => 'a stored value of the entry changed',
        'missing_entry' => 'no entry holds this position',
        'inserted_or_relinked' => 'the entry was inserted, renumbered or moved',
        'head_mismatch' => 'the head does not point at the last entry',
    ];

    public function __construct(
        private readonly VerifyAuditChainHandler $handler,
        private readonly RunAs $runAs,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $report = $this->runAs->system(Channel::Console, fn(): VerifyAuditChainOutput => ($this->handler)(new VerifyAuditChain()));

        $intactThrough = null === $report->intactThroughAt
            ? \sprintf('entry %d, where the check starts', $report->intactThrough)
            : \sprintf('entry %d (%s)', $report->intactThrough, $report->intactThroughAt);
        $notes = \array_values(\array_filter([
            \sprintf(
                'Last position: %d. Note it down outside this server: the chain cannot show the newest entries removed with the head set back.',
                $report->lastPosition,
            ),
            0 === $report->erased
                ? null
                : \sprintf('%d entries had their changed fields erased with a member; their place in the chain holds.', $report->erased),
            0 === $report->beforeChain ? null : \sprintf('%d entries come from before the chain and are not checked.', $report->beforeChain),
        ]));

        if (null === $report->breakReason) {
            $io->success(\sprintf('The audit chain is intact through %s.', $intactThrough));
            $io->text($notes);

            return Command::SUCCESS;
        }

        $io->error(\sprintf(
            'The audit chain breaks at entry %d: %s. It is intact through %s.',
            (int) $report->breakPosition,
            self::REASONS[$report->breakReason] ?? $report->breakReason,
            $intactThrough,
        ));
        $io->text($notes);

        return Command::FAILURE;
    }
}
