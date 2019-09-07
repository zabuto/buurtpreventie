<?php

namespace App\Traits;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Trait Blameable
 */
trait Blameable
{
    /**
     * @var UserInterface|null
     */
    protected $createdBy;

    /**
     * @var UserInterface|null
     */
    protected $updatedBy;

    /**
     * @var UserInterface|null
     */
    protected $deletedBy;

    /**
     * @return UserInterface|null
     */
    public function getCreatedBy(): ?UserInterface
    {
        return $this->createdBy;
    }

    /**
     * @param  UserInterface|null $createdBy
     */
    public function setCreatedBy(?UserInterface $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    /**
     * @return UserInterface|null
     */
    public function getUpdatedBy(): ?UserInterface
    {
        return $this->updatedBy;
    }

    /**
     * @param  UserInterface|null $updatedBy
     */
    public function setUpdatedBy(?UserInterface $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }

    /**
     * @return UserInterface|null
     */
    public function getDeletedBy(): ?UserInterface
    {
        return $this->deletedBy;
    }

    /**
     * @param  UserInterface|null $deletedBy
     */
    public function setDeletedBy(?UserInterface $deletedBy): void
    {
        $this->deletedBy = $deletedBy;
    }
}
