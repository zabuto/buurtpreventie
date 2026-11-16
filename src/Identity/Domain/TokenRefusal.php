<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * Why a presented API token lets nobody in. Only the log learns the reason; the client gets the same 401 for each,
 * so a refusal never tells whether a token exists.
 */
enum TokenRefusal: string
{
    case Malformed = 'malformed';
    case Unknown = 'unknown';
    case Revoked = 'revoked';
    case Expired = 'expired';
    case MemberInactive = 'member_inactive';
    case TokensDisabled = 'tokens_disabled';
}
