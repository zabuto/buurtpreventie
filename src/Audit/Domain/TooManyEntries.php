<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use App\Shared\Domain\InvalidValue;
use App\Shared\Domain\RuleViolationDetails;
use InvalidArgumentException;
use Override;

/**
 * More entries match than one file may hold. A file cut short would look complete, so the admin narrows the filter
 * by the count instead.
 */
final class TooManyEntries extends InvalidArgumentException implements InvalidValue, RuleViolationDetails
{
    private function __construct(
        string $message,
        private readonly int $count,
    ) {
        parent::__construct($message);
    }

    public static function counted(int $count, int $most): self
    {
        return new self(\sprintf('%d audit entries match; at most %d go at once.', $count, $most), $count);
    }

    /**
     * @return array{count: int}
     */
    #[Override]
    public function details(): array
    {
        return ['count' => $this->count];
    }
}
