<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
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

    /**
     * @param int $hstsMaxAge seconds a browser keeps to HTTPS for this host; 0 sends no HSTS. Opt-in per installation:
     *                        a browser that has it locks visitors out for that long if the certificate is lost. No
     *                        includeSubDomains, as other subdomains of the domain may lack HTTPS
     */
    public function __construct(
        #[Autowire(env: 'int:HSTS_MAX_AGE')]
        private int $hstsMaxAge,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!\str_starts_with($request->getPathInfo(), self::API_PREFIX)) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->add([
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ]);
        if ($this->hstsMaxAge > 0 && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=' . $this->hstsMaxAge);
        }
    }
}
