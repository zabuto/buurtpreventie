<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * No item of this kind has this id. Entry points turn it into their own answer, such as HTTP 404.
 */
final class NotFound extends DomainException
{
    public static function forId(string $kind, Uuid $id): self
    {
        return new self(\sprintf('No %s with id %s.', $kind, $id->toRfc4122()));
    }
}
