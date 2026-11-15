<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * What other modules ask about members, implemented by the Membership module, so they never reach into it.
 */
interface MemberDirectory
{
    /**
     * @return bool false for a deactivated, erased or unknown member
     */
    public function isActive(Uuid $memberId): bool;

    /**
     * @return list<Uuid> the active walkers who want to hear about rounds that seek a buddy
     */
    public function openRoundSubscribers(): array;

    /**
     * @param list<Uuid> $memberIds
     *
     * @return array<string, MemberProfile> by RFC 4122 id; an unknown id is missing
     */
    public function profilesOf(array $memberIds): array;
}
