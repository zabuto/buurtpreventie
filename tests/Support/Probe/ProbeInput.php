<?php

declare(strict_types=1);

namespace App\Tests\Support\Probe;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProbeInput
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name = '',
    ) {
    }
}
