<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Throwable;

/**
 * A domain rule refused the action. Entry points name it after the short class name in snake case (RoundIsFull is
 * round_is_full), so a module never needs to know how REST or MCP shows it.
 */
interface RuleViolation extends Throwable
{
}
