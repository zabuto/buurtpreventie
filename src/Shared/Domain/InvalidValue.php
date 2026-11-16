<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * A value in the request breaks a rule, whatever the state, such as a start in the past; REST answers 422.
 */
interface InvalidValue extends RuleViolation
{
}
