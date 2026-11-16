<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Channel;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Sets who acts for an entry point without HTTP authentication, such as a console command, the fixtures or the
 * scheduler. The previous token is restored afterwards, also when the operation fails, so an action never runs under
 * an identity left behind by another.
 */
final readonly class RunAs
{
    private const string FIREWALL = 'run_as';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The member counts as recently authenticated: whoever runs a console command proved who they are by reaching
     * the server.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function member(Actor $actor, Channel $channel, callable $operation): mixed
    {
        return $this->as(MemberUser::of($actor), $channel, $operation, recent: true);
    }

    /**
     * The app acting on its own: the scheduler through cron, whether a cron job or the tick endpoint started it; the
     * fixtures through console; the installer through web.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function system(Channel $channel, callable $operation): mixed
    {
        return $this->as(new SystemUser(), $channel, $operation);
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    private function as(UserInterface $user, Channel $channel, callable $operation, bool $recent = false): mixed
    {
        $token = new UsernamePasswordToken($user, self::FIREWALL, $user->getRoles());
        $token->setAttribute(TokenExecutionContext::CHANNEL, $channel);
        if ($recent) {
            TokenRecentAuthentication::stamp($token, $this->clock->now());
        }

        $previous = $this->tokenStorage->getToken();
        $this->tokenStorage->setToken($token);
        try {
            return $operation();
        } finally {
            $this->tokenStorage->setToken($previous);
        }
    }
}
