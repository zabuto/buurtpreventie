<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Infrastructure\Http\ProblemResponses;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\TokenRecentAuthentication;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the browser authenticators share: the token they hand out, stamped as a recent proof (BrowserTokenChannel
 * adds the channel), and the answers to a success and a failure, logged and recorded alike.
 */
final readonly class LoginResponses
{
    public function __construct(
        private LoginRecorder $recorder,
        private ProblemResponses $problems,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        private TranslatorInterface $translator,
    ) {
    }

    public function stamp(#[SensitiveParameter] TokenInterface $token): TokenInterface
    {
        TokenRecentAuthentication::stamp($token, $this->clock->now());

        return $token;
    }

    /**
     * @param LoginMethod|null $method null for a confirmation, which is no login
     */
    public function succeeded(Request $request, #[SensitiveParameter] TokenInterface $token, ?LoginMethod $method): Response
    {
        $user = $token->getUser();
        \assert($user instanceof MemberUser, 'The browser authenticators load a member.');

        if (null !== $method) {
            $this->recorder->loggedIn($user->memberId, $request->getClientIp(), $method);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function failed(Request $request, AuthenticationException $exception, LoginMethod $method): JsonResponse
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $this->logger->notice('Login refused: {reason}', ['reason' => 'throttled']);
            $minutes = $exception->getMessageData()['%minutes%'] ?? 1;

            return $this->problems->create(Response::HTTP_TOO_MANY_REQUESTS, 'too_many_requests', [
                'Retry-After' => (string) (60 * (\is_numeric($minutes) ? (int) $minutes : 1)),
            ]);
        }

        $refusal = $exception instanceof LoginRefused ? $exception : new LoginRefused('failed');
        $this->logger->notice('Login refused: {reason}', ['reason' => $refusal->reason]);
        if (null !== $refusal->blankField) {
            return $this->problems->create(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_input', violations: [[
                'field' => $refusal->blankField,
                'message' => $this->translator->trans('This value should not be blank.', domain: 'validators'),
            ]]);
        }

        if (null !== $refusal->memberId) {
            $this->recorder->failed($refusal->memberId, $request->getClientIp(), $method);
        }

        return $this->problems->create(
            Response::HTTP_UNAUTHORIZED,
            $refusal->problem,
            extensions: null === $refusal->attemptsLeft ? [] : ['attemptsLeft' => $refusal->attemptsLeft],
        );
    }
}
