<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Fake;

use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundRepository;
use App\Shared\Domain\NotFound;
use DateTimeImmutable;
use Override;
use Symfony\Component\Uid\Uuid;

final class InMemoryRoundRepository implements RoundRepository
{
    /** @var array<string, Round> */
    private array $rounds = [];

    #[Override]
    public function get(Uuid $id): Round
    {
        return $this->rounds[$id->toRfc4122()] ?? throw NotFound::forId('round', $id);
    }

    #[Override]
    public function add(Round $round): void
    {
        $this->rounds[$round->id()->toRfc4122()] = $round;
    }

    #[Override]
    public function remove(Round $round): void
    {
        unset($this->rounds[$round->id()->toRfc4122()]);
    }

    #[Override]
    public function upcomingWithWalker(Uuid $memberId, DateTimeImmutable $now): array
    {
        return \array_values(\array_filter(
            $this->rounds,
            static fn(Round $round): bool => !$round->hasStarted($now) && $round->isSignedUp($memberId),
        ));
    }

    public function has(Round $round): bool
    {
        return \array_key_exists($round->id()->toRfc4122(), $this->rounds);
    }
}
