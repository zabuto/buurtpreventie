<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Invitation;
use Override;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Logs a new member in once with the secret of an invitation on POST /api/v1/auth/invitation. The frontend reads
 * the secret from behind the # of the link and posts it, so it reaches no access log, and a virus scanner that opens
 * the link uses nothing up.
 */
final class InvitationAuthenticator extends AbstractAuthenticator
{
    public const string ROUTE = 'auth_invitation';

    public function __construct(
        private readonly InvitationCheck $check,
        private readonly LoginResponses $responses,
    ) {
    }

    #[Override]
    public function supports(Request $request): bool
    {
        return self::ROUTE === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    /**
     * The throttling counts by the hash of the secret, so the secret itself never becomes a cache key.
     */
    #[Override]
    public function authenticate(Request $request): SelfValidatingPassport
    {
        $payload = LoginPayload::of($request);
        $secret = $payload->text('secret');

        return new SelfValidatingPassport(
            new UserBadge(\bin2hex(Invitation::hash($secret)), fn(): UserInterface => $this->check->member($secret)),
            [new RememberMeBadge($payload->rememberMe())],
        );
    }

    #[Override]
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return $this->responses->stamp(parent::createToken($passport, $firewallName));
    }

    #[Override]
    public function onAuthenticationSuccess(Request $request, #[SensitiveParameter] TokenInterface $token, string $firewallName): Response
    {
        return $this->responses->succeeded($request, $token, LoginMethod::Invitation);
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return $this->responses->failed($request, $exception, LoginMethod::Invitation);
    }
}
