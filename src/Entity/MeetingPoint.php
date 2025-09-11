<?php declare(strict_types=1);

namespace App\Entity;

use App\Geo\ValueObject\Point;
use App\Repository\MeetingPointRepository;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table]
#[ORM\Entity(repositoryClass: MeetingPointRepository::class)]
#[UniqueEntity(fields: ['description'], message: 'lookup.description-already-in-use')]
class MeetingPoint extends AbstractBaseEntity implements Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $description = '';

    #[ORM\Column(type: 'point', nullable: true)]
    private ?Point $location;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = trim((string)$description);
    }

    public function getLocation(): ?Point
    {
        return $this->location;
    }

    public function setLocation(?Point $location): void
    {
        $this->location = $location;
    }

    public function __toString(): string
    {
        return $this->description;
    }
}
