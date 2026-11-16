<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Domain\Channel;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;

/**
 * Puts the web channel on every token of the browser firewall, also the one Symfony's remember-me authenticator
 * creates from the "stay logged in" cookie; without it the audit log would not know the channel. That token gets
 * no moment of proof, so it never counts as recent.
 */
#[AsEventListener(event: AuthenticationTokenCreatedEvent::class, dispatcher: 'security.event_dispatcher.browser')]
final readonly class BrowserTokenChannel
{
    public function __invoke(AuthenticationTokenCreatedEvent $event): void
    {
        $event->getAuthenticatedToken()->setAttribute(TokenExecutionContext::CHANNEL, Channel::Web);
    }
}
