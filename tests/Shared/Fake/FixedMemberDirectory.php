<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fake;

use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\MemberProfile;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class FixedMemberDirectory implements MemberDirectory
{
    /**
     * @param list<MemberProfile> $profiles
     * @param list<Uuid> $openRoundSubscribers
     */
    public function __construct(
        private array $profiles = [],
        private array $openRoundSubscribers = [],
    ) {
    }

    /**
     * An active member with a display name and full name, sharing no phone number.
     */
    public static function activeProfile(Uuid $memberId, string $displayName = 'Jan B.'): MemberProfile
    {
        return new MemberProfile($memberId, $displayName, $displayName . ' Bakker', null, active: true);
    }

    #[Override]
    public function isActive(Uuid $memberId): bool
    {
        return $this->profilesOf([$memberId])[$memberId->toRfc4122()]->active ?? false;
    }

    #[Override]
    public function openRoundSubscribers(): array
    {
        return $this->openRoundSubscribers;
    }

    #[Override]
    public function profilesOf(array $memberIds): array
    {
        $wanted = \array_map(static fn(Uuid $memberId): string => $memberId->toRfc4122(), $memberIds);
        $found = [];
        foreach ($this->profiles as $profile) {
            if (!\in_array($profile->memberId->toRfc4122(), $wanted, strict: true)) {
                continue;
            }

            $found[$profile->memberId->toRfc4122()] = $profile;
        }

        return $found;
    }
}
