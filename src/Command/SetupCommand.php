<?php

namespace App\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * SetupCommand
 */
class SetupCommand extends Command
{
    /**
     * @var OutputInterface|null
     */
    private $output;

    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('app:setup')
            ->setDescription('Set up the application.')
            ->addOption('force', null, InputOption::VALUE_OPTIONAL, '', false);
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $env = null;
        if ($input->hasOption('env')) {
            $env = $input->getOption('env');
        }

        $this->writeHeader($env);

        $force = $input->getOption('force');
        $dropArguments = [];
        if ($env === 'dev' || false !== $force) {
            $dropArguments = ['--force' => null];
        }

        $result = true;
        $result = $result && $this->runCommand('doctrine:database:drop', $dropArguments);
        $result = $result && $this->runCommand('doctrine:database:create');
        $result = $result && $this->runCommand('doctrine:schema:create');

        if ($env === 'dev') {
            $this->output->writeln(' Loading fixtures...');
            $result = $result && $this->runCommand('doctrine:fixtures:load', ['--append' => null]);
        }

        $this->writeResult($result);
    }

    /**
     * @param  string $name
     * @param  array  $arguments
     * @return boolean
     */
    private function runCommand(string $name, array $arguments = [])
    {
        try {
            $command = $this->getApplication()->find($name);

            $arguments['command'] = $name;

            $input = new ArrayInput($arguments);
            $input->setInteractive(false);

            $status = $command->run($input, $this->output);

            return ($status === 0) ? true : false;
        } catch (Exception $e) {
            $this->output->writeln(sprintf('<error>%s : %s</error>', $name, $e->getMessage()));

            return false;
        }
    }

    /**
     * @param  string $env
     */
    private function writeHeader($env)
    {
        $headerStyle = new OutputFormatterStyle('yellow', 'magenta', ['bold']);
        $this->output->getFormatter()->setStyle('header', $headerStyle);

        $header = sprintf(' Run application setup for %s environment ...', $env);

        $this->output->writeln([
            '<header>' . str_pad('', 120) . '</header>',
            '<header>' . str_pad($header, 120) . '</header>',
            '<header>' . str_pad('', 120) . '</header>',
        ]);
    }

    /**
     * @param  bool $result
     */
    private function writeResult($result)
    {
        if ($result) {
            $successStyle = new OutputFormatterStyle('green', null, ['bold']);
            $this->output->getFormatter()->setStyle('success', $successStyle);

            $this->output->writeln(['', '<success>Setup complete</success>']);
        } else {
            $this->output->writeln(['', '<error>Setup failed</error>']);
        }
    }
}
