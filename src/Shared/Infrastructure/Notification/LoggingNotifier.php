<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Notification;

use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use Override;
use Psr\Log\LoggerInterface;

/**
 * Logs each notification instead of delivering it. The log names the member by id only, so it holds no contact
 * details.
 */
final readonly class LoggingNotifier implements Notifier
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[Override]
    public function send(Notification $notification): void
    {
        $this->logger->info('Notification {type} for member {recipient}', [
            'type' => $notification->type,
            'recipient' => $notification->recipientId->toRfc4122(),
            'parameters' => $notification->parameters,
        ]);
    }
}
