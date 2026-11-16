<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Whether the member acting proved who they are in the last 15 minutes, with a password, a login code or an
 * invitation, so a sensitive action can ask for it. A session kept by "stay logged in" and a request with an API
 * token never count.
 *
 * @upgrade-8.4 Symfony's sudo mode takes this over.
 */
interface RecentAuthentication
{
    public function isRecent(): bool;
}
