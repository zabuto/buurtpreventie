<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Asks the voters whether the current actor may perform an action, so every entry point enforces the same rights.
 */
interface Authorization
{
    /**
     * @param string $action the voter attribute, such as round.change
     * @param object|null $subject what the action applies to, such as the round
     *
     * @throws AccessDenied
     */
    public function denyUnlessGranted(string $action, ?object $subject = null): void;
}
