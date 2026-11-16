<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * A refusal that tells the client what it needs to act on it, such as the item that already holds a name; entry
 * points add the details to their answer.
 */
interface RuleViolationDetails extends RuleViolation
{
    /**
     * @return array<string, scalar> never a secret or the personal data of someone else
     */
    public function details(): array;
}
