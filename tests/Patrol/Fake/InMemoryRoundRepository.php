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

    /**
     * The fake holds no locks: the database tests prove the locking.
     */
    #[Override]
    public function getForUpdate(Uuid $id): Round
    {
        return $this->get($id);
    }

    #[Override]
    public function between(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->sortedWhere(static fn(Round $round): bool => $round->startsAt() >= $from && $round->startsAt() < $to);
    }

    #[Override]
    public function upcoming(DateTimeImmutable $now): array
    {
        return $this->sortedWhere(static fn(Round $round): bool => !$round->hasStarted($now));
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

    /**
     * @return list<Round>
     */
    public function all(): array
    {
        return \array_values($this->rounds);
    }

    /**
     * @param callable(Round): bool $matches
     *
     * @return list<Round>
     */
    private function sortedWhere(callable $matches): array
    {
        $found = \array_values(\array_filter($this->rounds, $matches));
        \usort($found, static fn(Round $a, Round $b): int => $a->startsAt() <=> $b->startsAt());

        return $found;
    }
}
