<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\PasswordHasher;
use Override;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * The "auto" hasher from security.yaml. It verifies the bcrypt hashes the legacy app made, so members keep their password.
 */
final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(
        private PasswordHasherFactoryInterface $hasherFactory,
    ) {
    }

    #[Override]
    public function hash(#[SensitiveParameter] string $password): string
    {
        return $this->hasher()->hash($password);
    }

    #[Override]
    public function verify(string $hash, #[SensitiveParameter] string $password): bool
    {
        return $this->hasher()->verify($hash, $password);
    }

    private function hasher(): PasswordHasherInterface
    {
        return $this->hasherFactory->getPasswordHasher(PasswordAuthenticatedUserInterface::class);
    }
}
