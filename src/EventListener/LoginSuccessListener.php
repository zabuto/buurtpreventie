<?php declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class, method: 'onLoginSuccess')]
readonly class LoginSuccessListener
{
    public function __construct(private UserRepository $repo, private RouterInterface $router)
    {
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!($user instanceof User)) {
            return;
        }

        $route = null === $user->getLastLogin() ? 'account' : 'calendar';
        $this->repo->saveLastLogin($user);

        $event->setResponse(new RedirectResponse($this->router->generate($route)));
    }
}
