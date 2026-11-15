<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Domain\ReadAction;
use App\Shared\Domain\WriteAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Holds every handler to the definition of done: a write handler names its voter attribute and audit action, its
 * attribute has rows in the authorization matrix, and its test proves the audit entry. A handler without either
 * attribute fails, so a forgotten WriteAction cannot pass as a read.
 */
#[Group('integration')]
final class HandlerGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function handlers(): iterable
    {
        foreach (self::classesIn('UseCase/*/*Handler.php') as $class) {
            yield $class => [$class];
        }
    }

    /**
     * @param class-string $handler
     */
    #[DataProvider('handlers')]
    #[Test]
    public function every_handler_is_marked_as_either_write_or_read(string $handler): void
    {
        $reflection = new ReflectionClass($handler);

        $marks = \count($reflection->getAttributes(WriteAction::class)) + \count($reflection->getAttributes(ReadAction::class));

        $this->assertSame(1, $marks, $handler . ' needs exactly one of #[WriteAction] and #[ReadAction].');
    }

    /**
     * @param class-string $handler
     */
    #[DataProvider('handlers')]
    #[Test]
    public function every_write_action_has_rows_in_the_authorization_matrix(string $handler): void
    {
        $writeAction = self::writeAction($handler);
        if (null === $writeAction) {
            $this->expectNotToPerformAssertions();

            return;
        }

        $this->assertContains(
            $writeAction->authorization,
            AuthorizationMatrixTest::coveredAttributes(),
            $handler . ' asks an attribute the matrix does not cover.',
        );
    }

    /**
     * @param class-string $handler
     */
    #[DataProvider('handlers')]
    #[Test]
    public function every_write_handler_test_proves_the_audit_entry(string $handler): void
    {
        if (null === self::writeAction($handler)) {
            $this->expectNotToPerformAssertions();

            return;
        }

        $test = \preg_replace('/^App\\\\/', 'App\\Tests\\', $handler) . 'Test';
        $this->assertTrue(\class_exists($test), $handler . ' has no test ' . $test . '.');

        $recordingTests = \array_filter(
            new ReflectionClass($test)->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn(ReflectionMethod $method): bool => \str_starts_with($method->getName(), 'it_records_'),
        );
        $this->assertNotSame([], $recordingTests, $test . ' has no test starting with it_records_.');
    }

    /**
     * The matrix builds the voters itself; a voter it leaves out would decide unseen.
     */
    #[Test]
    public function the_authorization_matrix_asks_every_voter(): void
    {
        $inMatrix = \array_map(static fn(object $voter): string => $voter::class, AuthorizationMatrixTest::voters());

        $missing = \array_values(\array_diff(self::classesIn('Infrastructure/Security/*Voter.php'), $inMatrix));

        $this->assertSame([], $missing);
    }

    /**
     * @param class-string $handler
     */
    private static function writeAction(string $handler): ?WriteAction
    {
        $attributes = new ReflectionClass($handler)->getAttributes(WriteAction::class);

        return [] === $attributes ? null : $attributes[0]->newInstance();
    }

    /**
     * @return list<class-string>
     */
    private static function classesIn(string $pattern): array
    {
        $source = \dirname(__DIR__, 2) . '/src/';
        $classes = [];
        $files = \glob($source . '*/' . $pattern);
        foreach (false === $files ? [] : $files as $file) {
            $class = 'App\\' . \str_replace('/', '\\', \substr($file, \strlen($source), -4));
            \assert(\class_exists($class), 'Every PHP file under src/ declares the class its path names.');
            $classes[] = $class;
        }

        return $classes;
    }
}
