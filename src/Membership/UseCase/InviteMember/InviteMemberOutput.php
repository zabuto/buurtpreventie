<?php

declare(strict_types=1);

namespace App\Membership\UseCase\InviteMember;

use SensitiveParameter;

final readonly class InviteMemberOutput
{
    /**
     * @param string|null $secret of the invitation link, only for an admin inviting a member who never logged in
     */
    public function __construct(
        #[SensitiveParameter]
        public ?string $secret,
    ) {
    }
}
