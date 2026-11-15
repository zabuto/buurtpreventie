<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Who performs the current action and through which channel. The entry point fills it, so a handler never needs
 * to know whether REST, MCP, the scheduler or a console command called it.
 */
interface ExecutionContext
{
    public function channel(): Channel;

    /**
     * The member performing the action, with their roles, or null when the system acts on its own, such as a
     * scheduled reminder.
     */
    public function actor(): ?Actor;
}
