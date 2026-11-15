<?php

declare(strict_types=1);

namespace App\Alpha\Domain;

use Doctrine\ORM\EntityManagerInterface;

/** Violates the domain rule: only Doctrine mapping attributes may enter the domain, not the entity manager. */
final class ThingFinder
{
    public function __construct(public EntityManagerInterface $entityManager)
    {
    }
}
