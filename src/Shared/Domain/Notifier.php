<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Delivers notifications to members. Handlers decide who gets which message; how it reaches them is up to the
 * implementation.
 */
interface Notifier
{
    public function send(Notification $notification): void;
}
