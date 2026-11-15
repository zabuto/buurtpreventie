<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use App\Shared\Domain\LookupKey;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A kind of round report that walkers pick from a list. The name is unique on its lookup key, archived items
 * included: "Café" next to "cafe" in a list cannot be told apart.
 */
#[ORM\Entity]
final class Outcome
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private readonly Uuid $id;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 100, unique: true, options: ['collation' => 'utf8mb4_bin'])]
    private string $nameKey;

    #[ORM\Column]
    private bool $archived = false;

    private function __construct(
        string $name,
        #[ORM\Column(length: 20, enumType: OutcomeKind::class)]
        private readonly OutcomeKind $kind,
    ) {
        $this->id = Uuid::v7();
        $this->rename($name);
    }

    public static function create(string $name, OutcomeKind $kind): self
    {
        return new self($name, $kind);
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

    public function kind(): OutcomeKind
    {
        return $this->kind;
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

    public function archive(): void
    {
        $this->archived = true;
    }

    public function restore(): void
    {
        $this->archived = false;
    }
}
