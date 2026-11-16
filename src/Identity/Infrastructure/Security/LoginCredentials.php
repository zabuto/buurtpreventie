<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use SensitiveParameter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The address and password of a login request. A missing or non-text field is empty, so it fails like a wrong one.
 */
final readonly class LoginCredentials
{
    /** Symfony's hashers refuse longer passwords, which would end the dummy hash of a refusal in an error. */
    private const int MAX_PASSWORD_LENGTH = 4096;

    /**
     * @param array<string, mixed> $rememberMe the parameters of the remember-me badge
     */
    private function __construct(
        public string $email,
        #[SensitiveParameter]
        public string $password,
        public array $rememberMe,
    ) {
    }

    /**
     * @throws BadRequestHttpException for a body that is no JSON object
     * @throws LoginRefused for a password too long to hash
     */
    public static function of(Request $request): self
    {
        $payload = LoginPayload::of($request);
        $password = $payload->text('password');
        if (\strlen($password) > self::MAX_PASSWORD_LENGTH) {
            throw new LoginRefused('password_too_long');
        }

        return new self(\trim($payload->text('email')), $password, $payload->rememberMe());
    }
}
