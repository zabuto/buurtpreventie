<?php declare(strict_types=1);

namespace App\Traits;

use App\Entity\User;

trait Blameable
{
    protected ?User $createdBy = null;
    protected ?User $updatedBy = null;
    protected ?User $deletedBy = null;

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }

    public function getDeletedBy(): ?User
    {
        return $this->deletedBy;
    }

    public function setDeletedBy(?User $deletedBy): void
    {
        $this->deletedBy = $deletedBy;
    }
}
