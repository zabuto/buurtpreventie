<?php

declare(strict_types=1);

namespace App\Tests\Identity\Fake;

use LogicException;
use Override;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeDetails;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;

/**
 * Records for whom a "stay logged in" cookie would be signed.
 */
final class RecordingRememberMeHandler implements RememberMeHandlerInterface
{
    /** @var list<UserInterface> */
    public array $cookiesFor = [];

    #[Override]
    public function createRememberMeCookie(UserInterface $user): void
    {
        $this->cookiesFor[] = $user;
    }

    #[Override]
    public function consumeRememberMeCookie(RememberMeDetails $rememberMeDetails): UserInterface
    {
        throw new LogicException('Reading a cookie is no part of these tests.');
    }

    #[Override]
    public function clearRememberMeCookie(): void
    {
    }
}
