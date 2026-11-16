<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\UseCase\RequestLoginCode\RequestLoginCodeController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Holds requests for a login code to 3 per hour per address and 10 per hour per IP address, so nobody floods a
 * member's mailbox or tries addresses in bulk. Every request counts, whether the address belongs to a member or not,
 * so the limit does not tell either. The address is counted by a hash, so the cache holds no address.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class LoginCodeRequestThrottle
{
    public function __construct(
        #[Autowire(service: 'limiter.login_code_per_address')]
        private RateLimiterFactoryInterface $perAddress,
        #[Autowire(service: 'limiter.login_code_per_ip')]
        private RateLimiterFactoryInterface $perIp,
    ) {
    }

    /**
     * @throws TooManyRequestsHttpException with the seconds to wait in Retry-After
     */
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || RequestLoginCodeController::ROUTE !== $request->attributes->get('_route')) {
            return;
        }

        foreach ([$this->perIp->create($request->getClientIp()), $this->perAddress->create(self::addressKey($request))] as $limiter) {
            $limit = $limiter->consume();
            if (!$limit->isAccepted()) {
                $wait = \max(1, (int) \ceil((float) $limit->getRetryAfter()->format('U.u') - \microtime(true)));

                throw new TooManyRequestsHttpException($wait, 'Too many requests for a login code.');
            }
        }
    }

    private static function addressKey(Request $request): string
    {
        try {
            $email = $request->toArray()['email'] ?? '';
        } catch (JsonException) {
            $email = '';
        }

        return \hash('sha256', \mb_strtolower(\trim(\is_string($email) ? $email : '')));
    }
}
