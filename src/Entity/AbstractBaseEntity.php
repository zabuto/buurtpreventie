<?php declare(strict_types=1);

namespace App\Entity;

use App\Traits\Blameable;
use App\Traits\SoftDeletable;
use App\Traits\Timestampable;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\MappedSuperclass]
abstract class AbstractBaseEntity
{
    use Blameable, SoftDeletable, Timestampable;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Ignore]
    #[Map(if: false)]
    protected ?User $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Map(if: false)]
    protected ?DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Ignore]
    #[Map(if: false)]
    protected ?User $updatedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Map(if: false)]
    protected ?DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Ignore]
    #[Map(if: false)]
    protected ?User $deletedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Map(if: false)]
    protected ?DateTimeImmutable $deletedAt = null;
}
