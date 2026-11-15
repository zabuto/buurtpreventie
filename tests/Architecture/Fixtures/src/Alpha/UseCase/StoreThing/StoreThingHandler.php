<?php

declare(strict_types=1);

namespace App\Alpha\UseCase\StoreThing;

use Doctrine\ORM\EntityManagerInterface;

/** Violates the input and handler rule: a handler uses the Doctrine ORM instead of a repository interface. */
final class StoreThingHandler
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(StoreThing $input): void
    {
        $this->entityManager->flush();
    }
}
