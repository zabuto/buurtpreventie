<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Fake;

use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\MeetingPointRepository;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\NotFound;
use Override;
use Symfony\Component\Uid\Uuid;

final class InMemoryMeetingPointRepository implements MeetingPointRepository
{
    /** @var array<string, MeetingPoint> */
    private array $items = [];

    #[Override]
    public function get(Uuid $id): MeetingPoint
    {
        return $this->items[$id->toRfc4122()] ?? throw NotFound::forId('meeting point', $id);
    }

    #[Override]
    public function findByNameKey(LookupKey $nameKey): ?MeetingPoint
    {
        foreach ($this->items as $item) {
            if ($item->nameKey() === $nameKey->value) {
                return $item;
            }
        }

        return null;
    }

    #[Override]
    public function update(MeetingPoint $meetingPoint): void
    {
        $this->items[$meetingPoint->id()->toRfc4122()] = $meetingPoint;
    }

    #[Override]
    public function add(MeetingPoint $meetingPoint): void
    {
        $this->items[$meetingPoint->id()->toRfc4122()] = $meetingPoint;
    }

    public function has(MeetingPoint $meetingPoint): bool
    {
        return \array_key_exists($meetingPoint->id()->toRfc4122(), $this->items);
    }
}
