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

final class LoggingNotifierTest extends TestCase
{
    #[Test]
    public function it_logs_who_gets_which_message(): void
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

        new LoggingNotifier($logger)->send(new Notification($recipient, 'round.walker_withdrawn', ['roundId' => 'r-1']));

        $this->assertSame(
            [[
                'info',
                'Notification {type} for member {recipient}',
                [
                    'type' => 'round.walker_withdrawn',
                    'recipient' => '0192f3a0-0000-7000-8000-000000000001',
                    'parameters' => ['roundId' => 'r-1'],
                ],
            ]],
            $logger->records,
        );
    }
}
