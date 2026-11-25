<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\SystemUser;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserClassesTest extends TestCase
{
    /**
     * Symfony 8 drops eraseCredentials() from UserInterface. On a user class that marks it #[Override], PHP then
     * stops with a fatal error before the kernel boots, so every request and command fails.
     */
    #[Test]
    public function it_leaves_erase_credentials_unmarked_as_override_on_every_user_class(): void
    {
        $found = [];
        $marked = [];
        foreach (self::userClasses() as $class) {
            $found[] = $class->getName();
            if ($class->hasMethod('eraseCredentials') && [] !== $class->getMethod('eraseCredentials')->getAttributes(Override::class)) {
                $marked[] = $class->getName();
            }
        }

        $this->assertContains(MemberUser::class, $found, 'The scan of src/ finds the user classes.');
        $this->assertContains(SystemUser::class, $found, 'The scan of src/ finds the user classes.');
        $this->assertSame([], $marked);
    }

    /**
     * @return list<ReflectionClass<object>>
     */
    private static function userClasses(): array
    {
        $source = \dirname(__DIR__, 4) . '/src';
        $classes = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $name = 'App\\' . \str_replace('/', '\\', \substr($file->getPathname(), \strlen($source) + 1, -4));
            if (!\class_exists($name)) {
                continue;
            }

            $class = new ReflectionClass($name);
            if ($class->implementsInterface(UserInterface::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
