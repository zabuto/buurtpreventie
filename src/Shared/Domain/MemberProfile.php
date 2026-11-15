<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * What another module may know about a member to show who walks. The phone number is there only when the member
 * consents to sharing it; who may see it is up to the caller's policy.
 */
final readonly class MemberProfile
{
    /**
     * @param string|null $displayName null for an erased member, shown as "removed member"
     * @param string|null $name the full name, null for an erased member
     */
    public function __construct(
        public Uuid $memberId,
        public ?string $displayName,
        public ?string $name,
        public ?string $phone,
        public bool $active,
    ) {
    }
}
