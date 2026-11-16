<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\CurrentSession;
use App\Shared\Infrastructure\Security\MemberUser;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Puts the member with the new credentials version in the token, which the session stores at the end of the
 * request, so the next request finds the same user. A "stay logged in" cookie the request came with is signed anew.
 */
final readonly class TokenCurrentSession implements CurrentSession
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private MemberUserProvider $users,
        private RequestStack $requests,
        #[Autowire(service: 'security.authenticator.remember_me_handler.browser')]
        private RememberMeHandlerInterface $rememberMe,
    ) {
    }

    #[Override]
    public function keepAfterNewPassword(Uuid $memberId): void
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (null === $token || !$user instanceof MemberUser || !$user->memberId->equals($memberId)) {
            return;
        }

        $renewed = $this->users->loadUserByIdentifier($user->getUserIdentifier());
        $token->setUser($renewed);

        if (true === $this->requests->getMainRequest()?->cookies->has('bp_remember')) {
            $this->rememberMe->createRememberMeCookie($renewed);
        }
    }
}
