<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Infrastructure\Security\MemberUser;
use Override;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

/**
 * Makes the session of the member recent again with the password on POST /api/v1/auth/confirm, before a sensitive
 * action. Only the member of the session confirms: the address is not asked, so this proves nothing about anyone
 * else. A refusal leaves the session as it was. A confirmation is no login, so only a failure reaches the audit log.
 */
final class ConfirmPasswordAuthenticator extends AbstractAuthenticator
{
    public const string ROUTE = 'auth_confirm';

    /** Symfony's hashers refuse longer passwords. */
    private const int MAX_PASSWORD_LENGTH = 4096;

    public function __construct(
        private readonly PasswordCheck $check,
        private readonly MemberUserProvider $users,
        private readonly LoginResponses $responses,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    #[Override]
    public function supports(Request $request): bool
    {
        return self::ROUTE === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    /**
     * @throws LoginRefused unauthorized without a session, login_failed for a password too long to hash
     */
    #[Override]
    public function authenticate(Request $request): Passport
    {
        $member = $this->tokenStorage->getToken()?->getUser();
        if (!$member instanceof MemberUser) {
            throw new LoginRefused('no_session', problem: 'unauthorized');
        }

        $password = LoginPayload::of($request)->text('password');
        if (\strlen($password) > self::MAX_PASSWORD_LENGTH) {
            throw new LoginRefused('password_too_long', $member->memberId);
        }

        return new Passport(
            new UserBadge($member->getUserIdentifier(), $this->users->loadUserByIdentifier(...)),
            new CustomCredentials(
                fn(#[SensitiveParameter] mixed $password, UserInterface $user): bool => $this->check->password($user, $password),
                $password,
            ),
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
        return $this->responses->succeeded($request, $token, null);
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return $this->responses->failed($request, $exception, LoginMethod::Password);
    }
}
