<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Drops the X-Powered-By header that PHP adds when expose_php is on. It names the exact PHP version, which tells an
 * attacker which known vulnerabilities to try. expose_php itself can only be switched off in the server
 * configuration, which shared hosting often does not allow.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class HidePhpVersion
{
    public function __invoke(): void
    {
        \header_remove('X-Powered-By');
    }
}
