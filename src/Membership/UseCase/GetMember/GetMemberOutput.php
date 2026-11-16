<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetMember;

final readonly class GetMemberOutput
{
    /**
     * @param string|null $email null for an erased member, as are name, display name and contact details
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
        public bool $active,
        public bool $certified,
        public bool $consentContactShare,
        public bool $notifyOpenRounds,
    ) {
    }
}
