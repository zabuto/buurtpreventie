<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PHPat checks class dependencies, so empty folders and file names fall outside its reach; this test covers them.
 */
final class ModuleStructureTest extends TestCase
{
    private const string SOURCE_ROOT = __DIR__.'/../../src';
    private const string EXPLANATION = 'See docs/architecture.md#mapindeling.';
    private const array MODULE_FOLDERS = ['Domain', 'Infrastructure', 'UseCase'];
    private const array SHARED_FOLDERS = ['Domain', 'Infrastructure'];

    /**
     * @return iterable<string, array{string}>
     */
    public static function modules(): iterable
    {
        foreach (self::entries(self::SOURCE_ROOT) as $entry) {
            if ('Shared' !== $entry && is_dir(self::SOURCE_ROOT.'/'.$entry)) {
                yield $entry => [$entry];
            }
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function useCases(): iterable
    {
        foreach (self::modules() as [$module]) {
            foreach (self::entries(self::SOURCE_ROOT.'/'.$module.'/UseCase') as $useCase) {
                yield $module.'/'.$useCase => [$module, $useCase];
            }
        }
    }

    public function testSourceRootHoldsOnlyModulesSharedAndTheKernel(): void
    {
        foreach (self::entries(self::SOURCE_ROOT) as $entry) {
            self::assertTrue(
                is_dir(self::SOURCE_ROOT.'/'.$entry) || 'Kernel.php' === $entry,
                \sprintf('src/%s is neither a module folder nor Kernel.php. %s', $entry, self::EXPLANATION),
            );
        }
    }

    #[DataProvider('modules')]
    public function testEveryModuleHasExactlyDomainUseCaseAndInfrastructure(string $module): void
    {
        self::assertSame(
            self::MODULE_FOLDERS,
            self::folders(self::SOURCE_ROOT.'/'.$module),
            \sprintf('Module %s must contain exactly Domain/, UseCase/ and Infrastructure/. %s', $module, self::EXPLANATION),
        );
    }

    public function testSharedHasOnlyDomainAndInfrastructure(): void
    {
        self::assertSame(
            self::SHARED_FOLDERS,
            self::folders(self::SOURCE_ROOT.'/Shared'),
            'Shared is not a module: it holds Domain/ and Infrastructure/ only, no use cases. '.self::EXPLANATION,
        );
    }

    #[DataProvider('useCases')]
    public function testEveryUseCaseFolderHoldsOnlyItsFixedFiles(string $module, string $useCase): void
    {
        $folder = self::SOURCE_ROOT.'/'.$module.'/UseCase/'.$useCase;
        self::assertDirectoryExists($folder, \sprintf('%s/UseCase/%s must be a folder. %s', $module, $useCase, self::EXPLANATION));

        $allowed = array_map(
            static fn (string $suffix): string => $useCase.$suffix.'.php',
            ['', 'Handler', 'Controller', 'Tool', 'Output'],
        );
        $files = self::entries($folder);

        self::assertSame([], array_values(array_diff($files, $allowed)), \sprintf(
            '%s/UseCase/%s may only hold %s. %s',
            $module,
            $useCase,
            implode(', ', $allowed),
            self::EXPLANATION,
        ));
        self::assertContains($useCase.'.php', $files, \sprintf('Use case %s lacks its input class. %s', $useCase, self::EXPLANATION));
        self::assertContains($useCase.'Handler.php', $files, \sprintf('Use case %s lacks its handler. %s', $useCase, self::EXPLANATION));
    }

    /**
     * @return list<string>
     */
    private static function folders(string $path): array
    {
        return array_values(array_filter(
            self::entries($path),
            static fn (string $entry): bool => is_dir($path.'/'.$entry),
        ));
    }

    /**
     * Dotfiles such as .gitkeep only keep empty folders in Git and are ignored.
     *
     * @return list<string>
     */
    private static function entries(string $path): array
    {
        $entries = scandir($path);
        if (false === $entries) {
            return [];
        }

        $visible = array_values(array_filter($entries, static fn (string $entry): bool => !str_starts_with($entry, '.')));
        sort($visible);

        return $visible;
    }
}
