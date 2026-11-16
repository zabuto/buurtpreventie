<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * What one typed code did to a login challenge.
 */
enum CodeAttempt
{
    case Accepted;
    /** The code was wrong; the challenge may take another attempt. */
    case Wrong;
    /** Used, expired, out of attempts or replaced by a newer challenge: no code works any more. */
    case Ended;
}
