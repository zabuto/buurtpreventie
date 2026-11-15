<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Fake;

use App\Patrol\Domain\Outcome;
use App\Patrol\Domain\OutcomeRepository;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\NotFound;
use Override;
use Symfony\Component\Uid\Uuid;

final class InMemoryOutcomeRepository implements OutcomeRepository
{
    /** @var array<string, Outcome> */
    private array $items = [];

    #[Override]
    public function get(Uuid $id): Outcome
    {
        return $this->items[$id->toRfc4122()] ?? throw NotFound::forId('outcome', $id);
    }

    #[Override]
    public function findByNameKey(LookupKey $nameKey): ?Outcome
    {
        foreach ($this->items as $item) {
            if ($item->nameKey() === $nameKey->value) {
                return $item;
            }
        }

        return null;
    }

    #[Override]
    public function update(Outcome $outcome): void
    {
        $this->items[$outcome->id()->toRfc4122()] = $outcome;
    }

    #[Override]
    public function add(Outcome $outcome): void
    {
        $this->items[$outcome->id()->toRfc4122()] = $outcome;
    }

    public function has(Outcome $outcome): bool
    {
        return \array_key_exists($outcome->id()->toRfc4122(), $this->items);
    }
}
