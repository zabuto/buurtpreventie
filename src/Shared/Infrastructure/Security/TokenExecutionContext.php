<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Channel;
use App\Shared\Domain\ExecutionContext;
use LogicException;
use Override;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The execution context from Symfony's token storage, the one source of who acts. RunAs and the authenticators put
 * the channel on the token as an attribute.
 */
final readonly class TokenExecutionContext implements ExecutionContext
{
    public const string CHANNEL = 'channel';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @throws LogicException when the entry point set no channel, which would otherwise end up wrong in the audit log
     */
    #[Override]
    public function channel(): Channel
    {
        $token = $this->tokenStorage->getToken();
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
