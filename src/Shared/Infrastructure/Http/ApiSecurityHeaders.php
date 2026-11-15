<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The API answers with JSON only, so its responses may load, embed and frame nothing. Pages outside the API, such
 * as the installer, need their own policy.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class ApiSecurityHeaders
{
    private const string API_PREFIX = '/api/';

    public function __invoke(ResponseEvent $event): void
    {
        if (!\str_starts_with($event->getRequest()->getPathInfo(), self::API_PREFIX)) {
            return;
        }

        $event
            ->getResponse()
            ->headers->add([
                'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
                'Cross-Origin-Opener-Policy' => 'same-origin',
            ])
        ;
    }
}
