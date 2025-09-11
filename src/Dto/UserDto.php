<?php declare(strict_types=1);

namespace App\Dto;

final readonly class UserDto
{
    public function __construct(
        public string  $name,
        public string  $email,
        public ?string $token = null,
        public ?string $valid_until = null,
    )
    {
    }
}
