<?php

namespace App\Entity;

use App\Interfaces\ResultInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="App\Repository\ResultRepository")
 */
class Result extends AbstractBaseEntity implements ResultInterface
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int|null
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=50, unique=true)
     * @Assert\NotBlank()
     * @var string
     */
    private $description;

    /**
     * @ORM\Column(type="boolean")
     * @Assert\Type("bool")
     * @var bool
     */
    private $remarks = false;

    /**
     * @ORM\Column(type="boolean")
     * @Assert\Type("bool")
     * @var bool
     */
    private $incident = false;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param  string $description
     */
    public function setDescription($description): void
    {
        $this->description = $description;
    }

    /**
     * @return bool
     */
    public function isRemarks(): bool
    {
        return $this->remarks;
    }

    /**
     * @param  bool $remarks
     */
    public function setRemarks($remarks): void
    {
        $this->remarks = $remarks;
    }

    /**
     * @return bool
     */
    public function isIncident(): bool
    {
        return $this->incident;
    }

    /**
     * @param  bool $incident
     */
    public function setIncident($incident): void
    {
        $this->incident = $incident;
    }

    /**
     * @return boolean
     */
    public function allowRemarks()
    {
        return $this->remarks;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->description;
    }
}
