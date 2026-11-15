<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Catalogue;

use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * An archived meeting point stays on what used it, but is not offered for anything new.
 */
final class MeetingPointArchived extends DomainException
{
    public static function forItem(Uuid $id): self
    {
        return new self(\sprintf('The meeting point %s is archived.', $id->toRfc4122()));
    }
}
