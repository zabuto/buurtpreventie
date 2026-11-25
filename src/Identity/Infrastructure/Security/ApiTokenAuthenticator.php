<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\ApiTokenRepository;
use App\Identity\Domain\TokenRefusal;
use App\Identity\Domain\TokenScope;
use App\Identity\Infrastructure\Mcp\McpSwitch;
use App\Settings\UseCase\GetApiTokenAccess\GetApiTokenAccess;
use App\Settings\UseCase\GetApiTokenAccess\GetApiTokenAccessHandler;
use App\Shared\Domain\Channel;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\Transaction;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use Override;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticates every request on the token firewall by its `Authorization: Bearer` secret. It takes every request,
 * so a missing or broken token is refused and logged like a wrong one. The token is looked up by the hash of the
 * secret on every request, so a revocation, a deactivation or switching tokens off counts at once; the secret carries
 * no content of its own.
 */
final class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public const string SCOPES = 'scopes';
    public const string TOKEN_ID = 'token_id';

    private const string SECRET_PATTERN = '/^Bearer (bpt_[0-9a-f]{64})\z/';

    public function __construct(
        private readonly ApiTokenRepository $tokens,
        private readonly MemberDirectory $members,
        private readonly Transaction $transaction,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly GetApiTokenAccessHandler $access,
    ) {
    }

    #[Override]
    public function supports(Request $request): bool
    {
        return true;
    }

    #[Override]
    public function authenticate(Request $request): SelfValidatingPassport
    {
        if (!($this->access)(new GetApiTokenAccess())->allowed) {
            throw new ApiTokenRefused(TokenRefusal::TokensDisabled);
        }

        $token = $this->tokenOf((string) $request->headers->get('Authorization'));
        $identity = $this->members->identity($token->memberId());
        $now = $this->clock->now();

        $refusal = $token->refusalAt($now, $identity->active ?? false);
        if (null !== $refusal) {
            throw new ApiTokenRefused($refusal);
        }

        \assert(null !== $identity, 'An unknown member is inactive, which refused the token above.');

        $this->transaction->run(static fn(): bool => $token->markUsed($now));

        $passport = new SelfValidatingPassport(new UserBadge(
            $identity->memberId->toRfc4122(),
            static fn(): MemberUser => new MemberUser($identity->memberId, $identity->roles),
        ));
        $passport->setAttribute(self::SCOPES, $token->scopes());
        $passport->setAttribute(self::TOKEN_ID, $token->id()->toRfc4122());
        $passport->setAttribute(TokenExecutionContext::CHANNEL, McpSwitch::isMcp($request->getPathInfo()) ? Channel::Mcp : Channel::Api);

        return $passport;
    }

    #[Override]
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);
        $token->setAttribute(TokenExecutionContext::CHANNEL, $passport->getAttribute(TokenExecutionContext::CHANNEL, Channel::Api));
        $token->setAttribute(self::SCOPES, $passport->getAttribute(self::SCOPES, []));
        $token->setAttribute(self::TOKEN_ID, $passport->getAttribute(self::TOKEN_ID));

        return $token;
    }

    #[Override]
    public function onAuthenticationSuccess(Request $request, #[SensitiveParameter] TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $reason = $exception instanceof ApiTokenRefused ? $exception->refusal->value : 'failed';
        $this->logger->notice('API token refused: {reason}', ['reason' => $reason, 'path' => $request->getPathInfo()]);

        return new Response('', Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
    }

    /**
     * @throws ApiTokenRefused for a header without a secret in the generated format, or a secret nobody holds
     */
    private function tokenOf(#[SensitiveParameter] string $header): ApiToken
    {
        if (1 !== \preg_match(self::SECRET_PATTERN, $header, $matches)) {
            throw new ApiTokenRefused(TokenRefusal::Malformed);
        }

        return $this->tokens->bySecretHash(ApiToken::hash($matches[1])) ?? throw new ApiTokenRefused(TokenRefusal::Unknown);
    }

    /**
     * @return list<TokenScope>
     */
    public static function scopesOf(#[SensitiveParameter] TokenInterface $token): array
    {
        $scopes = $token->hasAttribute(self::SCOPES) ? $token->getAttribute(self::SCOPES) : [];

        return \is_array($scopes) ? \array_values(\array_filter($scopes, static fn(mixed $scope): bool => $scope instanceof TokenScope)) : [];
    }

    /**
     * The id of the API token behind this login, or null for a browser session.
     */
    public static function tokenIdOf(#[SensitiveParameter] ?TokenInterface $token): ?string
    {
        $tokenId = $token?->hasAttribute(self::TOKEN_ID) ? $token->getAttribute(self::TOKEN_ID) : null;

        return \is_string($tokenId) ? $tokenId : null;
    }
}
