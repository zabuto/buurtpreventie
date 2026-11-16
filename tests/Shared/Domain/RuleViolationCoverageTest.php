<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\InvalidValue;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\RuleViolation;
use App\Shared\Domain\StateConflict;
use App\Shared\Infrastructure\Http\ProblemCode;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Every refusal a member can cause reaches them as a problem with a title in both languages. An exception outside
 * these kinds becomes a 500 without detail, so a forgotten marker shows up here instead of as an error page.
 */
#[Group('integration')]
final class RuleViolationCoverageTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<Throwable>}>
     */
    public static function domainExceptions(): iterable
    {
        foreach (self::exceptionClasses() as $class) {
            if (!\is_subclass_of($class, DomainException::class) && !\is_subclass_of($class, RuleViolation::class)) {
                continue;
            }

            yield $class => [$class];
        }
    }

    /**
     * @return iterable<string, array{class-string<RuleViolation>, string}>
     */
    public static function violationsPerLocale(): iterable
    {
        foreach (self::exceptionClasses() as $class) {
            if (!\is_subclass_of($class, RuleViolation::class)) {
                continue;
            }

            foreach (['nl', 'en'] as $locale) {
                yield $class . ' ' . $locale => [$class, $locale];
            }
        }
    }

    /**
     * @param class-string<Throwable> $class
     */
    #[DataProvider('domainExceptions')]
    #[Test]
    public function every_domain_exception_is_a_conflict_an_invalid_value_or_not_found(string $class): void
    {
        $kinds = [
            \is_a($class, StateConflict::class, allow_string: true),
            \is_a($class, InvalidValue::class, allow_string: true),
            \is_a($class, NotFound::class, allow_string: true),
        ];

        $this->assertCount(1, \array_filter($kinds), $class . ' needs exactly one of StateConflict, InvalidValue or NotFound.');
    }

    /**
     * @param class-string<RuleViolation> $class
     */
    #[DataProvider('violationsPerLocale')]
    #[Test]
    public function every_rule_violation_has_a_title_in_each_language(string $class, string $locale): void
    {
        $this->assertArrayHasKey(self::code($class), self::titles($locale), $class . ' has no title in translations/problems.' . $locale . '.yaml.');
    }

    #[Test]
    public function no_two_rule_violations_share_a_code(): void
    {
        $codes = [];
        foreach (self::exceptionClasses() as $class) {
            if (!\is_subclass_of($class, RuleViolation::class)) {
                continue;
            }

            $codes[] = self::code($class);
        }

        $this->assertSame(\array_values(\array_unique($codes)), $codes);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function titles(string $locale): array
    {
        $titles = Yaml::parseFile(\dirname(__DIR__, 3) . '/translations/problems.' . $locale . '.yaml');
        self::assertIsArray($titles);

        return $titles;
    }

    /**
     * @param class-string<RuleViolation> $class
     */
    private static function code(string $class): string
    {
        return ProblemCode::of($class);
    }

    /**
     * @return list<class-string>
     */
    private static function exceptionClasses(): array
    {
        $source = \dirname(__DIR__, 3) . '/src/';
        $classes = [];
        $files = \glob($source . '{*,*/*,*/*/*}/Domain/{*,*/*}.php', \GLOB_BRACE);
        foreach (false === $files ? [] : $files as $file) {
            $class = 'App\\' . \str_replace('/', '\\', \substr($file, \strlen($source), -4));
            if (\class_exists($class) && \is_subclass_of($class, Throwable::class)) {
                $classes[] = $class;
            }
        }

        \sort($classes);

        return \array_values(\array_unique($classes));
    }
}
