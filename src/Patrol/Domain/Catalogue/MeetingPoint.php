<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Catalogue;

use App\Shared\Domain\LookupKey;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A suggested place to gather, picked from a list. The name is unique on its lookup key, archived items included:
 * "Café" next to "cafe" in a list cannot be told apart.
 *
 * The coordinates are two plain columns: Doctrine cannot leave an embedded object empty.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'meeting_point_name_key', columns: ['name_key'])]
final class MeetingPoint
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private readonly Uuid $id;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 100, options: ['collation' => 'utf8mb4_bin'])]
    private string $nameKey;

    #[ORM\Column(type: 'decimal', precision: 9, scale: 6, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 9, scale: 6, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column]
    private bool $archived = false;

    private function __construct(string $name)
    {
        $this->id = Uuid::v7();
        $this->rename($name);
    }

    public static function create(string $name): self
    {
        return new self($name);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function nameKey(): string
    {
        return $this->nameKey;
    }

    public function coordinates(): ?Coordinates
    {
        if (null === $this->latitude || null === $this->longitude) {
            return null;
        }

        return new Coordinates((float) $this->latitude, (float) $this->longitude);
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function rename(string $name): void
    {
        $this->nameKey = LookupKey::fromName($name)->value;
        $this->name = \trim($name);
    }

    public function placeAt(?Coordinates $coordinates): void
    {
        $this->latitude = null === $coordinates ? null : \number_format($coordinates->latitude, 6, '.', '');
        $this->longitude = null === $coordinates ? null : \number_format($coordinates->longitude, 6, '.', '');
    }

    /**
     * @throws MeetingPointArchived as an archived meeting point is not offered for a new start
     */
    public function ensureSelectable(): void
    {
        if ($this->archived) {
            throw MeetingPointArchived::forItem($this->id);
        }
    }

    public function archive(): void
    {
        $this->archived = true;
    }

    public function restore(): void
    {
        $this->archived = false;
    }
}
