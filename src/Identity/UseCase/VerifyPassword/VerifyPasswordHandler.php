<?php

declare(strict_types=1);

namespace App\Identity\UseCase\VerifyPassword;

use App\Identity\Domain\AccountRepository;
use App\Identity\Domain\PasswordHasher;
use App\Shared\Domain\ReadAction;
use Symfony\Component\Uid\Uuid;

/**
 * Tells whether a password belongs to a member. A member without an account or password never matches.
 */
#[ReadAction]
final readonly class VerifyPasswordHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private PasswordHasher $passwordHasher,
    ) {
    }

    public function __invoke(VerifyPassword $input): VerifyPasswordOutput
    {
        $hash = $this->accounts->ofMember(Uuid::fromString($input->memberId))?->passwordHash();

        return new VerifyPasswordOutput(null !== $hash && $this->passwordHasher->verify($hash, $input->password));
    }
}
