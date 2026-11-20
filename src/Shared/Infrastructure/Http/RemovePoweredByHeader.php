<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Removes the X-Powered-By header that PHP adds when expose_php is on: it names the language and its exact version,
 * which tells an attacker which known vulnerabilities to try. expose_php itself can only be switched off in the server
 * configuration, which shared hosting often does not allow. PHP on the command line keeps no response headers, so no
 * test can see this header disappear.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class RemovePoweredByHeader
{
    public function __invoke(ResponseEvent $event): void
    {
        \header_remove('X-Powered-By');
        $event->getResponse()->headers->remove('X-Powered-By');
    }
}
