<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fake;

use App\Shared\Domain\MemberNames;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class FixedMemberNames implements MemberNames
{
    /**
     * @param array<string, string> $names member id => display name
     */
    public function __construct(
        private array $names = [],
    ) {
    }

    #[Override]
    public function displayNameOf(Uuid $memberId): ?string
    {
        return $this->names[$memberId->toRfc4122()] ?? null;
    }
}
