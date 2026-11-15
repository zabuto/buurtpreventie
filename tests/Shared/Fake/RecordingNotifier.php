<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fake;

use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use Override;

final class RecordingNotifier implements Notifier
{
    /** @var list<Notification> */
    public array $sent = [];

    #[Override]
    public function send(Notification $notification): void
    {
        $this->sent[] = $notification;
    }
}
