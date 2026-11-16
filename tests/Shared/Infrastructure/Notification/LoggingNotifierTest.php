<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Notification;

use App\Shared\Domain\Notification;
use App\Shared\Infrastructure\Notification\LoggingNotifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Uid\Uuid;

/**
 * A parameter can be a login code or the secret of an invitation, which must never reach the log.
 */
final class LoggingNotifierTest extends TestCase
{
    #[Test]
    public function it_logs_who_gets_which_message_without_its_parameters(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{mixed, string|Stringable, array<mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, $message, $context];
            }
        };
        $recipient = Uuid::fromString('0192f3a0-0000-7000-8000-000000000001');

        new LoggingNotifier($logger)->send(new Notification($recipient, 'account.login_code', ['code' => '482 913']));

        $this->assertSame(
            [[
                'info',
                'Notification {type} for member {recipient}',
                [
                    'type' => 'account.login_code',
                    'recipient' => '0192f3a0-0000-7000-8000-000000000001',
                ],
            ]],
            $logger->records,
        );
    }
}
