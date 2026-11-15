<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs PHPStan over fixtures that break each architecture rule once, so a rule that stops firing fails here
 * instead of silently letting violations into src/. Every test runs its own analysis and shares no state.
 */
#[Group('integration')]
final class ArchitectureRulesTest extends TestCase
{
    private const string CONFIGURATION = __DIR__ . '/Fixtures/phpstan.neon';
    private const string FIXTURES = 'tests/Architecture/Fixtures/src/';

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function fixtures(): iterable
    {
        yield 'domain depending on infrastructure' => ['Alpha/Domain/Thing.php', ['domainDependsOnlyOnItsOwnDomainAndSharedDomain']];
        yield 'domain using its own domain and Shared\Domain' => ['Alpha/Domain/Name.php', []];
        yield 'handler depending on HTTP' => ['Alpha/UseCase/DoThing/DoThingHandler.php', ['inputAndHandlerStayFreeOfTransportAndInfrastructure']];
        yield 'controller calling another use case' => ['Alpha/UseCase/DoThing/DoThingController.php', ['controllerUsesOnlyItsOwnUseCaseAndHttp']];
        yield 'tool depending on HTTP' => ['Alpha/UseCase/DoThing/DoThingTool.php', ['toolUsesOnlyItsOwnUseCaseAndMcp']];
        yield 'output exposing a domain object' => ['Alpha/UseCase/DoThing/DoThingOutput.php', ['outputExposesNoDomainObjects']];
        yield 'module reaching into another domain' => ['Beta/Infrastructure/ThingReader.php', ['modulesMeetOnlyThroughUseCasesAndSharedDomain']];
        yield 'module calling another use case' => ['Beta/Infrastructure/OtherThingClient.php', []];
        yield 'Twig outside e-mail and installer' => ['Beta/Infrastructure/Report.php', ['twigOnlyRendersEmailsAndTheInstaller']];
        yield 'Twig in e-mail infrastructure' => ['Beta/Infrastructure/Mail/Mailer.php', []];
        yield 'Shared depending on a module' => [
            'Shared/Domain/ModuleAware.php',
            ['domainDependsOnlyOnItsOwnDomainAndSharedDomain', 'sharedDependsOnNoModule'],
        ];
        yield 'Shared\Domain on its own' => ['Shared/Domain/Identifier.php', []];
    }

    /**
     * @param list<string> $expectedRules
     */
    #[DataProvider('fixtures')]
    #[Test]
    public function each_rule_fires_only_on_its_violation(string $fixture, array $expectedRules): void
    {
        $phpstan = PhpStanProcess::analyse(self::CONFIGURATION, self::FIXTURES . $fixture);

        $firedRules = \array_values(\array_unique(\array_column($this->errors($phpstan), 'rule')));
        \sort($firedRules);

        $this->assertSame($expectedRules, $firedRules, $phpstan->describe());
    }

    #[Test]
    public function every_violation_points_to_the_architecture_document(): void
    {
        $phpstan = PhpStanProcess::analyse(self::CONFIGURATION);

        $errors = $this->errors($phpstan);

        $this->assertNotEmpty($errors, $phpstan->describe());
        foreach ($errors as $error) {
            $this->assertStringContainsString('docs/architecture.md#', $error['tip'], $error['rule']);
        }
    }

    /**
     * @return list<array{rule: string, tip: string}>
     */
    private function errors(PhpStanProcess $phpstan): array
    {
        $report = $phpstan->report();
        $this->assertIsArray($report, $phpstan->describe());
        $this->assertSame([], $report['errors'] ?? null, $phpstan->describe());
        $files = $report['files'] ?? null;
        $this->assertIsArray($files, $phpstan->describe());

        $errors = [];
        foreach ($files as $file) {
            $messages = \is_array($file) ? $file['messages'] ?? [] : [];
            $this->assertIsArray($messages, $phpstan->describe());
            foreach ($messages as $message) {
                $this->assertIsArray($message, $phpstan->describe());
                $errors[] = [
                    'rule' => $this->ruleMethod($message),
                    'tip' => \is_string($message['tip'] ?? null) ? $message['tip'] : '',
                ];
            }
        }

        return $errors;
    }

    /**
     * PHPat builds the identifier from "phpat.", the rule method and the yielded key, stripped of separators.
     *
     * @param array<mixed> $message
     */
    private function ruleMethod(array $message): string
    {
        $identifier = \is_string($message['identifier'] ?? null) ? $message['identifier'] : '';
        $methods = \array_filter(
            \get_class_methods(ArchitectureRules::class),
            static fn(string $method): bool => \str_starts_with($identifier, 'phpat.' . $method),
        );
        $this->assertCount(1, $methods, 'Not traceable to a single PHPat rule: ' . $identifier);

        return \array_values($methods)[0];
    }
}
