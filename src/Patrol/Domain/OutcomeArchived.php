<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * An archived outcome stays on what used it, but is not offered for anything new.
 */
final class OutcomeArchived extends DomainException
{
    public static function forItem(Uuid $id): self
    {
        return new self(\sprintf('The outcome %s is archived.', $id->toRfc4122()));
    }
}
