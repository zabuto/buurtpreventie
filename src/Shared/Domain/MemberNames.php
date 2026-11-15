<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * The display name of a member, implemented by the Membership module, so other modules can record or show who acted
 * without reaching into Membership.
 */
interface MemberNames
{
    /**
     * @return string|null null for an erased or unknown member
     */
    public function displayNameOf(Uuid $memberId): ?string;
}
