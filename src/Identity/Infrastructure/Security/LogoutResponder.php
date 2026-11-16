<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Answers a logout with an empty 204 for the frontend, before Symfony's default listener would redirect.
 */
#[AsEventListener(event: LogoutEvent::class, priority: 65, dispatcher: 'security.event_dispatcher.browser')]
final readonly class LogoutResponder
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new Response(status: Response::HTTP_NO_CONTENT));
    }
}
