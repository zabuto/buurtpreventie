<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * How an action came in; the audit log records it with every entry.
 */
enum Channel: string
{
    case Web = 'web';
    case Api = 'api';
    case Mcp = 'mcp';
    case Cron = 'cron';
    case Console = 'console';
}
