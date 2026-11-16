<?php

declare(strict_types=1);

namespace App\Audit\Domain;

enum ChainBreakReason: string
{
    /** A stored value of the entry differs from what its hash covers. */
    case ChangedContent = 'changed_content';

    /** No entry holds the next position. */
    case MissingEntry = 'missing_entry';

    /** The entry was never part of the chain at this place: inserted, renumbered or moved. */
    case InsertedOrRelinked = 'inserted_or_relinked';

    /** The head names another position or hash than the last entry. */
    case HeadMismatch = 'head_mismatch';
}
