<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Runs PHPStan over fixtures that break each architecture rule once, so a rule that stops firing fails here
 * instead of silently letting violations into src/.
 */
#[Group('integration')]
final class ArchitectureRulesTest extends TestCase
{
    private const string PROJECT_ROOT = __DIR__.'/../..';
    private const string FIXTURES = 'tests/Architecture/Fixtures';

    /** @var array<string, list<array{rule: string, tip: string}>>|null */
    private static ?array $errorsByFile = null;

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function fixtures(): iterable
    {
        yield 'domain depending on infrastructure' => ['src/Alpha/Domain/Thing.php', ['testDomainDependsOnlyOnItsOwnDomainAndSharedDomain']];
        yield 'domain using its own domain and Shared\Domain' => ['src/Alpha/Domain/Name.php', []];
        yield 'handler depending on HTTP' => ['src/Alpha/UseCase/DoThing/DoThingHandler.php', ['testInputAndHandlerStayFreeOfTransportAndInfrastructure']];
        yield 'controller calling another use case' => ['src/Alpha/UseCase/DoThing/DoThingController.php', ['testControllerUsesOnlyItsOwnUseCaseAndHttp']];
        yield 'tool depending on HTTP' => ['src/Alpha/UseCase/DoThing/DoThingTool.php', ['testToolUsesOnlyItsOwnUseCaseAndMcp']];
        yield 'output exposing a domain object' => ['src/Alpha/UseCase/DoThing/DoThingOutput.php', ['testOutputExposesNoDomainObjects']];
        yield 'module reaching into another domain' => ['src/Beta/Infrastructure/ThingReader.php', ['testModulesMeetOnlyThroughUseCasesAndSharedDomain']];
        yield 'module calling another use case' => ['src/Beta/Infrastructure/OtherThingClient.php', []];
        yield 'Twig outside e-mail and installer' => ['src/Beta/Infrastructure/Report.php', ['testTwigOnlyRendersEmailsAndTheInstaller']];
        yield 'Twig in e-mail infrastructure' => ['src/Beta/Infrastructure/Mail/Mailer.php', []];
        yield 'Shared depending on a module' => ['src/Shared/Domain/ModuleAware.php', [
            'testDomainDependsOnlyOnItsOwnDomainAndSharedDomain',
            'testSharedDependsOnNoModule',
        ]];
        yield 'Shared\Domain on its own' => ['src/Shared/Domain/Identifier.php', []];
    }

    /**
     * @param list<string> $expectedRules
     */
    #[DataProvider('fixtures')]
    public function test_each_rule_fires_only_on_its_violation(string $file, array $expectedRules): void
    {
        $errors = self::errorsByFile()[$file] ?? [];
        $firedRules = array_values(array_unique(array_column($errors, 'rule')));
        sort($firedRules);

        self::assertSame($expectedRules, $firedRules);
    }

    public function test_every_violation_points_to_the_architecture_document(): void
    {
        $errors = array_merge(...array_values(self::errorsByFile()));

        self::assertNotEmpty($errors);
        foreach ($errors as $error) {
            self::assertStringContainsString('docs/architecture.md#', $error['tip'], $error['rule']);
        }
    }

    /**
     * @return array<string, list<array{rule: string, tip: string}>>
     */
    private static function errorsByFile(): array
    {
        if (null !== self::$errorsByFile) {
            return self::$errorsByFile;
        }

        $phpstan = PhpStanProcess::analyse(self::PROJECT_ROOT.'/'.self::FIXTURES.'/phpstan.neon');
        $report = $phpstan->report();
        self::assertIsArray($report, $phpstan->describe());
        self::assertSame([], $report['errors'] ?? null, $phpstan->describe());
        self::assertIsArray($report['files'] ?? null, $phpstan->describe());

        $fixturesRoot = realpath(self::PROJECT_ROOT.'/'.self::FIXTURES);
        self::assertIsString($fixturesRoot);

        $errorsByFile = [];
        foreach ($report['files'] as $path => $file) {
            self::assertIsString($path);
            self::assertIsArray($file);
            self::assertIsArray($file['messages'] ?? null);
            $relativePath = substr($path, \strlen($fixturesRoot) + 1);
            foreach ($file['messages'] as $message) {
                self::assertIsArray($message);
                $errorsByFile[$relativePath][] = [
                    'rule' => self::ruleMethod($message),
                    'tip' => \is_string($message['tip'] ?? null) ? $message['tip'] : '',
                ];
            }
        }

        return self::$errorsByFile = $errorsByFile;
    }

    /**
     * PHPat builds the identifier from "phpat.", the rule method and the yielded key, stripped of separators.
     *
     * @param array<mixed> $message
     */
    private static function ruleMethod(array $message): string
    {
        $identifier = \is_string($message['identifier'] ?? null) ? $message['identifier'] : '';
        $methods = array_filter(
            get_class_methods(ArchitectureRules::class),
            static fn (string $method): bool => str_starts_with($identifier, 'phpat.'.$method),
        );
        self::assertCount(1, $methods, 'Not traceable to a single PHPat rule: '.$identifier);

        return array_values($methods)[0];
    }
}
