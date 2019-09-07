<?php

namespace App\Traits;

use DateTime;

/**
 * Trait SoftDeletable
 */
trait SoftDeletable
{
    /**
     * @var DateTime|null
     */
    protected $deletedAt;

    /**
     * Marks entity as deleted
     */
    public function delete(): void
    {
        $this->deletedAt = new DateTime();
    }

    /**
     * Restore entity by undeleting it
     */
    public function restore(): void
    {
        $this->deletedAt = null;
    }

    /**
     * @return DateTime|null
     */
    public function getDeletedAt(): ?DateTime
    {
        return $this->deletedAt;
    }

    /**
     * @param  DateTime|null $deletedAt
     */
    public function setDeletedAt(?DateTime $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        if (null !== $this->deletedAt) {
            return true;
        }

        return false;
    }
}
