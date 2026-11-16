<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetOwnProfile;

final readonly class GetOwnProfileOutput
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public ?string $email,
        public ?string $name,
        public ?string $displayName,
        public ?string $phone,
        public ?string $address,
        public array $roles,
        public bool $consentContactShare,
        public bool $notifyOpenRounds,
        public bool $certified,
    ) {
    }
}
