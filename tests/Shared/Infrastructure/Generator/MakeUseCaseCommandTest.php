<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Generator;

use App\Shared\Infrastructure\Generator\MakeUseCaseCommand;
use App\Shared\Infrastructure\Generator\UseCaseTemplates;
use App\Tests\Architecture\PhpStanProcess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class MakeUseCaseCommandTest extends TestCase
{
    private const string PROJECT_ROOT = __DIR__.'/../../../..';

    private string $sourceRoot;

    protected function setUp(): void
    {
        $this->sourceRoot = sys_get_temp_dir().'/make-use-case-'.bin2hex(random_bytes(4)).'/src';
        foreach (['Domain', 'UseCase', 'Infrastructure'] as $folder) {
            mkdir($this->sourceRoot.'/Patrol/'.$folder, 0o777, recursive: true);
        }
        foreach (['Domain', 'Infrastructure'] as $folder) {
            mkdir($this->sourceRoot.'/Shared/'.$folder, 0o777, recursive: true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeRecursively(\dirname($this->sourceRoot));
    }

    public function test_writes_the_rendered_templates_into_a_new_use_case_folder(): void
    {
        $expected = new UseCaseTemplates()->render('Patrol', 'SignUpForRound');

        $tester = $this->generate('Patrol', 'SignUpForRound');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        foreach ($expected as $file => $code) {
            self::assertStringEqualsFile($this->sourceRoot.'/Patrol/UseCase/SignUpForRound/'.$file, $code);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'lower camel case' => ['signUpForRound'];
        yield 'underscore' => ['Sign_Up'];
        yield 'hyphen' => ['Sign-Up'];
        yield 'namespace separator' => ['Patrol\SignUp'];
        yield 'path traversal' => ['../SignUp'];
    }

    #[DataProvider('invalidNames')]
    public function test_refuses_a_name_that_is_not_pascal_case(string $name): void
    {
        $tester = $this->generate('Patrol', $name);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame(['Domain', 'Infrastructure', 'UseCase'], $this->visibleEntries($this->sourceRoot.'/Patrol'));
        self::assertSame([], $this->visibleEntries($this->sourceRoot.'/Patrol/UseCase'));
    }

    public function test_refuses_a_module_that_does_not_exist(): void
    {
        $tester = $this->generate('Rounds', 'SignUpForRound');

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertDirectoryDoesNotExist($this->sourceRoot.'/Rounds');
    }

    public function test_refuses_shared_because_it_holds_no_use_cases(): void
    {
        $tester = $this->generate('Shared', 'Clock');

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertDirectoryDoesNotExist($this->sourceRoot.'/Shared/UseCase');
    }

    public function test_leaves_an_existing_use_case_untouched(): void
    {
        $folder = $this->sourceRoot.'/Patrol/UseCase/SignUpForRound';
        mkdir($folder);
        file_put_contents($folder.'/SignUpForRound.php', 'existing');

        $tester = $this->generate('Patrol', 'SignUpForRound');

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame('existing', file_get_contents($folder.'/SignUpForRound.php'));
        self::assertSame(['SignUpForRound.php'], $this->visibleEntries($folder));
    }

    /**
     * Guards the templates themselves: whatever the generator writes must satisfy the same checks as src/.
     */
    public function test_generated_use_case_passes_phpstan_at_level_max_including_the_architecture_rules(): void
    {
        $this->generate('Patrol', 'SignUpForRound');

        $projectRoot = (string) realpath(self::PROJECT_ROOT);
        $config = \dirname($this->sourceRoot).'/phpstan.neon';
        file_put_contents($config, <<<NEON
            includes:
                - {$projectRoot}/vendor/phpat/phpat/extension.neon
                - {$projectRoot}/vendor/phpstan/phpstan-deprecation-rules/rules.neon
                - {$projectRoot}/tests/Architecture/architecture.neon
            parameters:
                level: max
                phpVersion: 80400
                paths:
                    - {$this->sourceRoot}
                architectureSourceRoot: {$this->sourceRoot}
            NEON);

        $phpstan = PhpStanProcess::analyse($config);

        self::assertSame(0, $phpstan->exitCode, $phpstan->describe());
        $totals = $phpstan->report()['totals'] ?? null;
        self::assertIsArray($totals, $phpstan->describe());
        self::assertSame(0, $totals['file_errors'] ?? null, $phpstan->describe());
    }

    private function generate(string $module, string $name): CommandTester
    {
        $tester = new CommandTester(new MakeUseCaseCommand($this->sourceRoot));
        $tester->execute(['module' => $module, 'name' => $name]);

        return $tester;
    }

    /**
     * @return list<string>
     */
    private function visibleEntries(string $path): array
    {
        return array_values(array_filter(
            (array) scandir($path),
            static fn (mixed $entry): bool => \is_string($entry) && !str_starts_with($entry, '.'),
        ));
    }

    private function removeRecursively(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            \assert($entry instanceof \SplFileInfo);
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($path);
    }
}
