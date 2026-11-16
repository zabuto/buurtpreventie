<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * The session the action runs in. A new password ends every session of the member; the one that set it takes the
 * new password along, so the member stays logged in where they changed it.
 */
interface CurrentSession
{
    /**
     * Does nothing when the session belongs to someone else or there is none.
     */
    public function keepAfterNewPassword(Uuid $memberId): void;
}
