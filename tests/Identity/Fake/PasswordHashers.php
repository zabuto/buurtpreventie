<?php

declare(strict_types=1);

namespace App\Tests\Identity\Fake;

use App\Identity\Infrastructure\Security\SymfonyPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * The real hasher with the cheapest settings, as the test environment configures it.
 */
final readonly class PasswordHashers
{
    public static function cheap(): SymfonyPasswordHasher
    {
        return new SymfonyPasswordHasher(new PasswordHasherFactory([
            PasswordAuthenticatedUserInterface::class => ['algorithm' => 'auto', 'cost' => 4, 'time_cost' => 3, 'memory_cost' => 10],
        ]));
    }
}
