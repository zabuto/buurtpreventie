<?php

namespace App\Entity;

use App\Geo\ValueObject\Point;
use App\Interfaces\MeetingPointInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="App\Repository\MeetingPointRepository")
 */
class MeetingPoint extends AbstractBaseEntity implements MeetingPointInterface
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int|null
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=100, unique=true)
     * @Assert\NotBlank()
     * @var string
     */
    private $description;

    /**
     * @ORM\Column(type="point", nullable=true)
     * @var Point
     */
    private $location;

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
     * @return Point|null
     */
    public function getLocation(): ?Point
    {
        return $this->location;
    }

    /**
     * @param  Point|null $location
     */
    public function setLocation($location): void
    {
        $this->location = $location;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->description;
    }
}
