<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mcp;

use App\Identity\Domain\TokenScope;
use App\Identity\Infrastructure\Security\ApiTokenAuthenticator;
use App\Identity\Infrastructure\Security\InsufficientScope;
use App\Identity\Infrastructure\Security\TokenLimits;
use LogicException;
use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Override;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Holds a tool call to the scopes of the token: read for a tool that only reads, write for every other. Every request
 * to the MCP server is a POST, so the scope check per HTTP method (TokenScopeGuard) only asks read there, and the
 * limits per token (TokenRateLimit) count it as a read; a tool that writes counts toward the write limit here. The
 * voters decide on the roles after this.
 */
final readonly class ScopedReferenceHandler implements ReferenceHandlerInterface
{
    public function __construct(
        private ReferenceHandlerInterface $inner,
        private TokenStorageInterface $tokenStorage,
        private TokenLimits $limits,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @throws InsufficientScope
     * @throws TooManyRequestsHttpException for a tool that writes above the write limit of the token
     * @throws LogicException for a tool without annotations, which would leave its scope a guess
     */
    #[Override]
    public function handle(ElementReference $reference, array $arguments): mixed
    {
        if ($reference instanceof ToolReference) {
            $required = self::requiredScope($reference);
            $this->denyUnlessScoped($required);
            if (TokenScope::Write === $required) {
                $this->countWrite();
            }
        }

        return $this->inner->handle($reference, $arguments);
    }

    public static function requiredScope(ToolReference $reference): TokenScope
    {
        $readOnly = $reference->tool->annotations->readOnlyHint
            ?? throw new LogicException(\sprintf('The MCP tool "%s" declares no readOnlyHint.', $reference->tool->name));

        return $readOnly ? TokenScope::Read : TokenScope::Write;
    }

    private function denyUnlessScoped(TokenScope $required): void
    {
        $token = $this->tokenStorage->getToken();
        if (null !== $token && \in_array($required, ApiTokenAuthenticator::scopesOf($token), strict: true)) {
            return;
        }

        throw new InsufficientScope();
    }

    private function countWrite(): void
    {
        $tokenId = ApiTokenAuthenticator::tokenIdOf($this->tokenStorage->getToken());
        if (null !== $tokenId) {
            $this->limits->write($tokenId);
        }
    }
}
