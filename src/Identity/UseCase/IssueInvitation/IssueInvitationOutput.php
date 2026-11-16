<?php

declare(strict_types=1);

namespace App\Identity\UseCase\IssueInvitation;

use SensitiveParameter;

final readonly class IssueInvitationOutput
{
    /**
     * @param string|null $secret only for an admin inviting a member who never logged in, to hand on without mail
     */
    public function __construct(
        #[SensitiveParameter]
        public ?string $secret,
    ) {
    }
}
