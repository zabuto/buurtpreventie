<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\Unchanged;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnchangedTest extends TestCase
{
    #[Test]
    public function it_names_the_fields_that_carry_a_value_or_null(): void
    {
        $input = new readonly class('Kerk', null, Unchanged::Field) {
            public function __construct(
                public string|Unchanged $name,
                public float|Unchanged|null $latitude,
                public float|Unchanged|null $longitude,
            ) {
            }
        };

        $this->assertSame(['name', 'latitude'], Unchanged::fieldsSetIn($input));
    }
}
