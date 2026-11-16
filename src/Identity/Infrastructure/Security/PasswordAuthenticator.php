<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Domain\Channel;
use App\Shared\Infrastructure\Http\ProblemResponses;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use Override;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
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
        private readonly LoginRecorder $recorder,
        private readonly ProblemResponses $problems,
        private readonly LoggerInterface $logger,
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
            new UserBadge($credentials->email, fn(string $email): MemberUser => $this->check->activeMember($email, $credentials->password)),
            new CustomCredentials(
                fn(#[SensitiveParameter] mixed $password, UserInterface $user): bool => $this->check->password($user, $password),
                $credentials->password,
            ),
        );
    }

    #[Override]
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);
        $token->setAttribute(TokenExecutionContext::CHANNEL, Channel::Web);

        return $token;
    }

    #[Override]
    public function onAuthenticationSuccess(Request $request, #[SensitiveParameter] TokenInterface $token, string $firewallName): Response
    {
        $user = $token->getUser();
        \assert($user instanceof MemberUser, 'The user badge of this authenticator loads a member.');

        $this->recorder->loggedIn($user->memberId, $request->getClientIp());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $this->logger->notice('Login refused: {reason}', ['reason' => 'throttled']);
            $minutes = $exception->getMessageData()['%minutes%'] ?? 1;

            return $this->problems->create(Response::HTTP_TOO_MANY_REQUESTS, 'too_many_requests', [
                'Retry-After' => (string) (60 * (\is_numeric($minutes) ? (int) $minutes : 1)),
            ]);
        }

        $reason = $exception instanceof LoginRefused ? $exception->reason : 'failed';
        $this->logger->notice('Login refused: {reason}', ['reason' => $reason]);

        $memberId = $exception instanceof LoginRefused ? $exception->memberId : null;
        if (null !== $memberId) {
            $this->recorder->failed($memberId, $request->getClientIp());
        }

        return $this->problems->create(Response::HTTP_UNAUTHORIZED, 'login_failed');
    }
}
