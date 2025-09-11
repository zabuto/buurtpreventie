<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\ResultRepository;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table]
#[ORM\Entity(repositoryClass: ResultRepository::class)]
#[UniqueEntity(fields: ['description'], message: 'lookup.description-already-in-use')]
class Result extends AbstractBaseEntity implements Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $description = '';

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $remarks = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $incident = false;

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

    public function isRemarks(): bool
    {
        return $this->remarks;
    }

    public function setRemarks(bool $remarks): void
    {
        $this->remarks = $remarks;
    }

    public function isIncident(): bool
    {
        return $this->incident;
    }

    public function setIncident(bool $incident): void
    {
        $this->incident = $incident;
    }

    public function allowRemarks(): bool
    {
        return $this->remarks;
    }

    public function __toString(): string
    {
        return $this->description;
    }
}
