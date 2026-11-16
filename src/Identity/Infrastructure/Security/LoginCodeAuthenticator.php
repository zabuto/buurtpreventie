<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

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
 * Logs a member in with a code from the mail on POST /api/v1/auth/login-code/verify, together with the challenge id
 * the browser kept when it asked for the code. A wrong code answers with the attempts left; a challenge that no
 * longer takes a code answers with a problem of its own, so the screen offers a new code.
 */
final class LoginCodeAuthenticator extends AbstractAuthenticator
{
    public const string ROUTE = 'auth_login_code_verify';

    public function __construct(
        private readonly LoginCodeCheck $check,
        private readonly LoginResponses $responses,
    ) {
    }

    #[Override]
    public function supports(Request $request): bool
    {
        return self::ROUTE === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    /**
     * @throws LoginRefused for a request without a challenge id, which no user badge takes
     */
    #[Override]
    public function authenticate(Request $request): SelfValidatingPassport
    {
        $payload = LoginPayload::of($request);
        $challengeId = $payload->text('challengeId');
        $code = $payload->text('code');
        if ('' === $challengeId) {
            throw new LoginRefused('unknown_challenge', problem: 'login_code_ended');
        }

        return new SelfValidatingPassport(
            new UserBadge(\substr($challengeId, 0, 64), fn(): UserInterface => $this->check->member($challengeId, $code)),
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
        return $this->responses->succeeded($request, $token, LoginMethod::Code);
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return $this->responses->failed($request, $exception, LoginMethod::Code);
    }
}
