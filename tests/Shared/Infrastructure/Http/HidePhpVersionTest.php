<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\HidePhpVersion;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * PHP on the command line keeps no response headers, so a test cannot see the header disappear; it proves that
 * every response passes the listener.
 */
#[Group('integration')]
final class HidePhpVersionTest extends KernelTestCase
{
    #[Test]
    public function it_runs_on_every_response(): void
    {
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $listeners = $dispatcher->getListeners(KernelEvents::RESPONSE);

        $this->assertNotEmpty(\array_filter(
            $listeners,
            static fn(mixed $listener): bool => \is_array($listener) && $listener[0] instanceof HidePhpVersion,
        ));
    }
}
