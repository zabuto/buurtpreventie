<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Console;

use App\SampleData\Infrastructure\Fixtures\DemoSet;
use App\SampleData\Infrastructure\Fixtures\EdgeSet;
use App\SampleData\Infrastructure\Fixtures\FixtureSet;
use App\SampleData\Infrastructure\Fixtures\TestSet;
use App\SampleData\Infrastructure\Fixtures\Timeline;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Only in dev and test: it empties the database first, and no production installation should offer that.
 */
#[AsCommand(name: 'app:fixtures:load', description: 'Empties the database and loads a set of made-up sample data')]
#[When(env: 'dev')]
#[When(env: 'test')]
final class LoadFixturesCommand extends Command
{
    /** @var array<string, FixtureSet> */
    private readonly array $sets;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Timeline $timeline,
        private readonly ClockInterface $clock,
        DemoSet $demo,
        TestSet $test,
        EdgeSet $edge,
    ) {
        // Before the parent constructor, which calls configure() and lists the sets.
        $this->sets = ['demo' => $demo, 'test' => $test, 'edge' => $edge];
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('set', InputArgument::REQUIRED, \sprintf('The set to load: %s', \implode(', ', \array_keys($this->sets))));
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('set');
        if (!\is_string($name) || !\array_key_exists($name, $this->sets)) {
            $io->error(\sprintf('Choose a set from %s.', \implode(', ', \array_keys($this->sets))));

            return Command::INVALID;
        }

        if ($input->isInteractive() && !$io->confirm('This empties the database. Continue?', default: false)) {
            return Command::FAILURE;
        }

        new ORMPurger($this->entityManager)->purge();
        $this->entityManager->clear();

        $now = $this->clock->now();
        $this->timeline->begin($now);
        $this->sets[$name]->load($now);

        $io->success(\sprintf('Loaded the %s set.', $name));

        return Command::SUCCESS;
    }
}
