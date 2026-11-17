<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mcp;

use App\Identity\Infrastructure\Security\ApiTokenAuthenticator;
use App\Settings\UseCase\GetMcpAccess\GetMcpAccess;
use App\Settings\UseCase\GetMcpAccess\GetMcpAccessHandler;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Lets mcp:server act as the member of the API token in MCP_TOKEN, checked by the authenticator of the HTTP server,
 * so STDIO knows no rule of its own: the same switches, expiry, revocation, scopes and mcp channel. The process checks
 * the token once, when it starts; it runs on the machine of whoever holds the token.
 */
#[AsEventListener(event: ConsoleEvents::COMMAND)]
final readonly class StdioAuthentication
{
    private const string COMMAND = 'mcp:server';

    public function __construct(
        private GetMcpAccessHandler $access,
        private ApiTokenAuthenticator $authenticator,
        private TokenStorageInterface $tokenStorage,
        #[Autowire(env: 'default::MCP_TOKEN')]
        #[SensitiveParameter]
        private ?string $secret = null,
    ) {
    }

    /**
     * @throws RuntimeException when MCP is off or the token is missing or refused; the command does not start
     */
    public function __invoke(ConsoleCommandEvent $event): void
    {
        if (self::COMMAND !== $event->getCommand()?->getName()) {
            return;
        }

        if (!($this->access)(new GetMcpAccess())->allowed) {
            throw new RuntimeException('MCP is off for this association; an admin switches it on in the settings (mcpAccess).');
        }

        if (null === $this->secret || '' === $this->secret) {
            throw new RuntimeException('Set MCP_TOKEN to an API token of the member the server acts as.');
        }

        $request = Request::create(McpSwitch::PATH, 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->secret]);
        try {
            $passport = $this->authenticator->authenticate($request);
        } catch (AuthenticationException $exception) {
            $this->authenticator->onAuthenticationFailure($request, $exception);

            throw new RuntimeException('The API token in MCP_TOKEN was refused; the log says why.', previous: $exception);
        }

        $this->tokenStorage->setToken($this->authenticator->createToken($passport, 'mcp'));
    }
}
