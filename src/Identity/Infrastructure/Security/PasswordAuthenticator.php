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
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

/**
 * Logs a member in with e-mail address and password on POST /api/v1/auth/login. The member and the password are
 * checked as passport steps, so the login throttling, which runs before them, stops an attempt before any hashing.
 * Every refusal gets the same answer, so it does not tell whether an address belongs to a member; PasswordCheck
 * keeps the timing equal.
 */
final class PasswordAuthenticator extends AbstractAuthenticator
{
    public const string ROUTE = 'auth_login';

    public function __construct(
        private readonly PasswordCheck $check,
        private readonly LoginResponses $responses,
    ) {
    }

    #[Override]
    public function supports(Request $request): bool
    {
        return self::ROUTE === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    #[Override]
    public function authenticate(Request $request): Passport
    {
        $credentials = LoginCredentials::of($request);

        return new Passport(
            new UserBadge($credentials->email, fn(string $email): UserInterface => $this->check->activeMember($email, $credentials->password)),
            new CustomCredentials(
                fn(#[SensitiveParameter] mixed $password, UserInterface $user): bool => $this->check->password($user, $password),
                $credentials->password,
            ),
            [new RememberMeBadge($credentials->rememberMe)],
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
        return $this->responses->succeeded($request, $token, LoginMethod::Password);
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return $this->responses->failed($request, $exception, LoginMethod::Password);
    }
}
