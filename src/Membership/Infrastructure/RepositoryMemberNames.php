<?php

declare(strict_types=1);

namespace App\Membership\Infrastructure;

use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\MemberNames;
use App\Shared\Domain\NotFound;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class RepositoryMemberNames implements MemberNames
{
    public function __construct(
        private MemberRepository $members,
    ) {
    }

    #[Override]
    public function displayNameOf(Uuid $memberId): ?string
    {
        try {
            return $this->members->get($memberId)->displayName();
        } catch (NotFound) {
            return null;
        }
    }
}
