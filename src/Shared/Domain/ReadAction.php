<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Attribute;

/**
 * Marks a handler that only reads, so a test can tell a forgotten WriteAction from a handler that needs none.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ReadAction
{
}
