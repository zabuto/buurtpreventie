<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Generator;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Development tool only: release builds run in prod, where this command is not registered.
 */
#[AsCommand(
    name: 'app:make:use-case',
    description: 'Creates a use-case folder with input, handler, controller, tool and output',
    help: 'Creates src/<Module>/UseCase/<Name>/ following docs/architecture.md#adding-a-feature.',
)]
#[When(env: 'dev')]
#[When(env: 'test')]
final class MakeUseCaseCommand extends Command
{
    private const string PASCAL_CASE = '/^[A-Z][A-Za-z0-9]*$/';

    public function __construct(
        #[Autowire('%kernel.project_dir%/src')]
        private readonly string $sourceRoot,
        private readonly UseCaseTemplates $templates = new UseCaseTemplates(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('module', InputArgument::REQUIRED, 'Existing module under src/, for example Patrol')
            ->addArgument('name', InputArgument::REQUIRED, 'Use-case name in PascalCase, for example SignUpForRound')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $module = $input->getArgument('module');
        $name = $input->getArgument('name');
        \assert(\is_string($module) && \is_string($name), 'Both arguments are required strings.');

        if (1 !== \preg_match(self::PASCAL_CASE, $name)) {
            $io->error(\sprintf('"%s" is not a PascalCase name such as SignUpForRound.', $name));

            return Command::INVALID;
        }
        if ('Shared' === $module || 1 !== \preg_match(self::PASCAL_CASE, $module) || !\is_dir($this->sourceRoot . '/' . $module . '/UseCase')) {
            $io->error(\sprintf('"%s" is not a module. Modules: %s.', $module, \implode(', ', $this->modules())));

            return Command::INVALID;
        }

        $folder = \sprintf('%s/%s/UseCase/%s', $this->sourceRoot, $module, $name);
        if (\file_exists($folder)) {
            $io->error(\sprintf('src/%s/UseCase/%s already exists; nothing was written.', $module, $name));

            return Command::FAILURE;
        }

        \mkdir($folder);
        $created = [];
        foreach ($this->templates->render($module, $name) as $file => $contents) {
            \file_put_contents($folder . '/' . $file, $contents);
            $created[] = \sprintf('src/%s/UseCase/%s/%s', $module, $name, $file);
        }

        $io->listing($created);
        $io->success(
            'Fill in the input and output, write the handler test first, and set the route. Delete the controller or tool when the use case has no REST or MCP entry.',
        );

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function modules(): array
    {
        $modules = \glob($this->sourceRoot . '/*/UseCase', \GLOB_ONLYDIR);

        return \array_map(
            static fn(string $path): string => \basename(\dirname($path)),
            false === $modules ? [] : $modules,
        );
    }
}
