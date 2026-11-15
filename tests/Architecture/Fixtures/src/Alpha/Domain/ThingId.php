<?php

declare(strict_types=1);

namespace App\Alpha\Domain;

use Symfony\Component\Uid\Uuid;

/** Complies: domain code may use Symfony Uid for identifiers. */
final class ThingId
{
    public function __construct(public Uuid $value)
    {
    }
}
