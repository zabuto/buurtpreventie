<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Authorization;
use Override;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Lets Symfony's access decision manager and the voters of every module decide for the current token. Without a
 * token, such as in a console command that set none, the voters see an anonymous user and the default strategy
 * refuses.
 */
final readonly class SymfonyAuthorization implements Authorization
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Override]
    public function denyUnlessGranted(string $action, ?object $subject = null): void
    {
        if ($this->authorizationChecker->isGranted($action, $subject)) {
            return;
        }

        throw AccessDenied::forAction($action);
    }
}
