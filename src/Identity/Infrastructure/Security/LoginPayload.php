<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The JSON body of a request to a browser authenticator. A missing or non-text field reads as empty, so it fails
 * like a wrong one.
 */
final readonly class LoginPayload
{
    /** The remember_me_parameter of the browser firewall. */
    public const string REMEMBER_ME = 'rememberMe';

    /**
     * @param array<array-key, mixed> $fields
     */
    private function __construct(
        public array $fields,
    ) {
    }

    /**
     * @throws BadRequestHttpException for a body that is no JSON object
     */
    public static function of(Request $request): self
    {
        try {
            return new self($request->toArray());
        } catch (JsonException) {
            throw new BadRequestHttpException('A login takes a JSON object.');
        }
    }

    /**
     * The parameters of the remember-me badge: "rememberMe": true in the body keeps the member logged in.
     *
     * @return array<string, mixed>
     */
    public function rememberMe(): array
    {
        return [self::REMEMBER_ME => $this->fields[self::REMEMBER_ME] ?? null];
    }

    public function text(string $field): string
    {
        $value = $this->fields[$field] ?? null;

        return \is_string($value) ? $value : '';
    }
}
