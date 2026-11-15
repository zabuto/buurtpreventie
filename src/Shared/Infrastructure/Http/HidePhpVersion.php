<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Replaces the X-Powered-By header that PHP adds when expose_php is on with a value without a version. PHP's value
 * names the exact version, which tells an attacker which known vulnerabilities to try; expose_php itself can only
 * be switched off in the server configuration, which shared hosting often does not allow.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class HidePhpVersion
{
    public function __invoke(ResponseEvent $event): void
    {
        \header_remove('X-Powered-By');
        $event->getResponse()->headers->set('X-Powered-By', 'PHP Symfony');
    }
}
