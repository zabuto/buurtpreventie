<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Notification;

use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use App\Shared\Infrastructure\Notification\AfterCommitNotifier;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingNotifier;
use App\Tests\Support\RecordingLogger;
use DomainException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

final class AfterCommitNotifierTest extends TestCase
{
    private const string JAN = '0198f5a2-0000-7000-8000-000000000001';
    private const string PIET = '0198f5a2-0000-7000-8000-000000000002';

    #[Test]
    public function it_sends_a_notification_only_once_the_transaction_has_committed(): void
    {
        $transaction = new ImmediateTransaction();
        $delivery = new RecordingNotifier();
        $notifier = new AfterCommitNotifier($delivery, $transaction, new RecordingLogger());
        $notification = self::notification(self::JAN);

        $sentDuringTheRun = $transaction->run(static function () use ($notifier, $notification, $delivery): int {
            $notifier->send($notification);

            return \count($delivery->sent);
        });

        $this->assertSame([0, [$notification]], [$sentDuringTheRun, $delivery->sent]);
    }

    #[Test]
    public function it_sends_nothing_when_the_transaction_rolls_back(): void
    {
        $transaction = new ImmediateTransaction();
        $delivery = new RecordingNotifier();
        $notifier = new AfterCommitNotifier($delivery, $transaction, new RecordingLogger());
        $refusal = new DomainException('The round is full.');

        $caught = null;
        try {
            $transaction->run(static function () use ($notifier, $refusal): never {
                $notifier->send(self::notification(self::JAN));

                throw $refusal;
            });
        } catch (DomainException $exception) {
            $caught = $exception;
        }

        $this->assertSame([$refusal, []], [$caught, $delivery->sent]);
    }

    #[Test]
    public function it_sends_at_once_outside_a_transaction(): void
    {
        $delivery = new RecordingNotifier();
        $notifier = new AfterCommitNotifier($delivery, new ImmediateTransaction(), new RecordingLogger());
        $notification = self::notification(self::JAN);

        $notifier->send($notification);

        $this->assertSame([$notification], $delivery->sent);
    }

    /**
     * The change is stored by the time the mail fails, so the member who made it gets no error, and every other
     * member still gets their message; the log is where the admin finds what went wrong.
     */
    #[Test]
    public function it_logs_a_failed_delivery_and_still_delivers_the_other_notifications(): void
    {
        $transaction = new ImmediateTransaction();
        $failure = new RuntimeException('Connection to "mail.example.org:587" refused.');
        $delivery = new class($failure, self::JAN) implements Notifier {
            /** @var list<Notification> */
            public array $sent = [];

            public function __construct(
                private readonly RuntimeException $failure,
                private readonly string $unreachable,
            ) {
            }

            #[Override]
            public function send(Notification $notification): void
            {
                if ($this->unreachable === $notification->recipientId->toRfc4122()) {
                    throw $this->failure;
                }

                $this->sent[] = $notification;
            }
        };
        $logger = new RecordingLogger();
        $notifier = new AfterCommitNotifier($delivery, $transaction, $logger);
        $toPiet = self::notification(self::PIET);

        $transaction->run(static function () use ($notifier, $toPiet): void {
            $notifier->send(self::notification(self::JAN));
            $notifier->send($toPiet);
        });

        $this->assertSame([$toPiet], $delivery->sent);
        $this->assertSame(
            [[
                'level' => 'error',
                'message' => 'Notification {type} for member {recipient} not delivered',
                'context' => ['type' => 'round.reminder', 'recipient' => self::JAN, 'exception' => $failure],
            ]],
            $logger->records,
        );
    }

    private static function notification(string $recipient): Notification
    {
        return new Notification(Uuid::fromString($recipient), 'round.reminder', ['roundIds' => 'r1']);
    }
}
