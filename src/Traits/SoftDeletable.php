<?php declare(strict_types=1);

namespace App\Traits;

use DateTimeImmutable;

trait SoftDeletable
{
    protected ?DateTimeImmutable $deletedAt = null;
    protected bool $hardDelete = false;

    /**
     * Marks entity as deleted
     */
    public function delete(): void
    {
        $this->deletedAt = new DateTimeImmutable();
    }

    /**
     * Restore entity by undeleting it
     */
    public function restore(): void
    {
        $this->deletedAt = null;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function isHardDelete(): bool
    {
        return $this->hardDelete;
    }

    public function doHardDelete(): void
    {
        $this->hardDelete = true;
    }
}
