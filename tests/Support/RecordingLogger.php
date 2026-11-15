<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps what the app logged, so a test can prove that a refusal reached the log and what it said.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<array-key, mixed>}> */
    public private(set) array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => \is_string($level) ? $level : 'unknown', 'message' => (string) $message, 'context' => $context];
    }
}
