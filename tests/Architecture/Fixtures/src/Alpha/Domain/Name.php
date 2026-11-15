<?php

declare(strict_types=1);

namespace App\Alpha\Domain;

use App\Shared\Domain\Identifier;

/** Complies: domain code may depend on its own domain and on Shared\Domain. */
final class Name
{
    public function __construct(public Identifier $id, public ?Thing $thing = null)
    {
    }
}
