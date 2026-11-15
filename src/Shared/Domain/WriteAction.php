<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Attribute;

/**
 * Marks a handler that changes something. It names the voter attribute it asks and the audit action it records, so
 * a test can hold every write handler to both.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class WriteAction
{
    public function __construct(
        public string $authorization,
        public string $audit,
    ) {
    }
}
