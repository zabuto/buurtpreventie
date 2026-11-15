<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * The request is fine on its own, but the current state forbids it, such as a full round; REST answers 409.
 */
interface StateConflict extends RuleViolation
{
}
