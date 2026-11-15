<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Generator;

use App\Shared\Infrastructure\Generator\MakeUseCaseCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class MakeUseCaseCommandTest extends TestCase
{
    private const string PROJECT_ROOT = __DIR__.'/../../../..';

    private string $sourceRoot;

    protected function setUp(): void
    {
        $this->sourceRoot = sys_get_temp_dir().'/make-use-case-'.bin2hex(random_bytes(4)).'/src';
        foreach (['Domain', 'UseCase', 'Infrastructure'] as $folder) {
            mkdir($this->sourceRoot.'/Patrol/'.$folder, 0o777, true);
        }
        foreach (['Domain', 'Infrastructure'] as $folder) {
            mkdir($this->sourceRoot.'/Shared/'.$folder, 0o777, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeRecursively(\dirname($this->sourceRoot));
    }

    public function testCreatesInputHandlerControllerToolAndOutputInTheUseCaseFolder(): void
    {
        $tester = $this->generate('Patrol', 'SignUpForRound');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        foreach (['', 'Handler', 'Controller', 'Tool', 'Output'] as $suffix) {
            $file = $this->sourceRoot.'/Patrol/UseCase/SignUpForRound/SignUpForRound'.$suffix.'.php';
            self::assertFileExists($file);
            $code = (string) file_get_contents($file);
            self::assertStringContainsString('namespace App\Patrol\UseCase\SignUpForRound;', $code);
            self::assertStringContainsString('final readonly class SignUpForRound'.$suffix."\n", $code);
        }
    }

    public function testControllerStartsAsPostOnTheKebabCasedName(): void
    {
        $this->generate('Patrol', 'SignUpForRound');

        $controller = (string) file_get_contents($this->sourceRoot.'/Patrol/UseCase/SignUpForRound/SignUpForRoundController.php');
        self::assertStringContainsString("#[Route('/sign-up-for-round', name: 'patrol_sign_up_for_round', methods: ['POST'])]", $controller);
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
    public function testRefusesANameThatIsNotPascalCase(string $name): void
    {
        $tester = $this->generate('Patrol', $name);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame(['Domain', 'Infrastructure', 'UseCase'], $this->visibleEntries($this->sourceRoot.'/Patrol'));
        self::assertSame([], $this->visibleEntries($this->sourceRoot.'/Patrol/UseCase'));
    }

    public function testRefusesAModuleThatDoesNotExist(): void
    {
        $tester = $this->generate('Rounds', 'SignUpForRound');

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertDirectoryDoesNotExist($this->sourceRoot.'/Rounds');
    }

    public function testRefusesSharedBecauseItHoldsNoUseCases(): void
    {
        $tester = $this->generate('Shared', 'Clock');

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertDirectoryDoesNotExist($this->sourceRoot.'/Shared/UseCase');
    }

    public function testLeavesAnExistingUseCaseUntouched(): void
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
    public function testGeneratedUseCasePassesPhpstanAtLevelMaxIncludingTheArchitectureRules(): void
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
                tmpDir: {$this->sourceRoot}/../phpstan-cache
                paths:
                    - {$this->sourceRoot}
                architectureSourceRoot: {$this->sourceRoot}
            NEON);

        exec(\sprintf(
            'cd %s && %s vendor/bin/phpstan analyse --configuration=%s --error-format=raw --no-progress --memory-limit=512M 2>/dev/null',
            escapeshellarg($projectRoot),
            escapeshellarg(\PHP_BINARY),
            escapeshellarg($config),
        ), $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));
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
