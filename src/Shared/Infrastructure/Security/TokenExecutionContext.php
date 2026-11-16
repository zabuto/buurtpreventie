<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Channel;
use App\Shared\Domain\ExecutionContext;
use LogicException;
use Override;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The execution context from Symfony's token storage, the one source of who acts. RunAs and the authenticators put
 * the channel on the token as an attribute. An HTTP request without a token runs on the web channel without an actor,
 * such as a request for a login code: a request with an API token is either authenticated or refused before it gets
 * this far.
 */
final readonly class TokenExecutionContext implements ExecutionContext
{
    public const string CHANNEL = 'channel';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requests,
    ) {
    }

    /**
     * @throws LogicException when the entry point set no channel, which the audit log would otherwise record wrongly
     */
    #[Override]
    public function channel(): Channel
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token && null !== $this->requests->getMainRequest()) {
            return Channel::Web;
        }

        $channel = null !== $token && $token->hasAttribute(self::CHANNEL) ? $token->getAttribute(self::CHANNEL) : null;
        if (!$channel instanceof Channel) {
            throw new LogicException('No entry point set the channel; use RunAs or an authenticator that sets it.');
        }

        return $channel;
    }

    #[Override]
    public function actor(): ?Actor
    {
        return TokenActor::of($this->tokenStorage->getToken());
    }
}
