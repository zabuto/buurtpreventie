<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * One value an association sets for itself, by name. A plain name and text value keeps branding and texts free of
 * a schema change each.
 */
#[ORM\Entity]
final class Setting
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 100)]
        private readonly string $name,
        #[ORM\Column(type: 'text')]
        private string $value,
    ) {
    }

    public static function of(string $name, string $value): self
    {
        return new self($name, $value);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function change(string $value): void
    {
        $this->value = $value;
    }
}
