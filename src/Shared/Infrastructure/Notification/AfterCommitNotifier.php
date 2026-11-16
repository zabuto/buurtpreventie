<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Notification;

use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use App\Shared\Domain\Transaction;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Hands each notification on once the change it is about has been committed, so an undone change never sends a
 * message. A delivery that fails after the commit is logged, not thrown: the change is stored, and an error would
 * tell the member it was not, and stop the messages to everyone after them.
 */
final readonly class AfterCommitNotifier implements Notifier
{
    public function __construct(
        #[Autowire(service: MailNotifier::class)]
        private Notifier $delivery,
        private Transaction $transaction,
        private LoggerInterface $logger,
    ) {
    }

    #[Override]
    public function send(Notification $notification): void
    {
        $this->transaction->afterCommit(function () use ($notification): void {
            try {
                $this->delivery->send($notification);
            } catch (Throwable $exception) {
                $this->logger->error('Notification {type} for member {recipient} not delivered', [
                    'type' => $notification->type,
                    'recipient' => $notification->recipientId->toRfc4122(),
                    'exception' => $exception,
                ]);
            }
        });
    }
}
