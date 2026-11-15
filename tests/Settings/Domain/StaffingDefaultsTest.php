<?php

declare(strict_types=1);

namespace App\Tests\Settings\Domain;

use App\Settings\Domain\StaffingDefaults;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StaffingDefaultsTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidPairs(): iterable
    {
        yield 'maximum below the minimum' => [3, 2];
        yield 'minimum below one' => [0, 7];
    }

    #[DataProvider('invalidPairs')]
    #[Test]
    public function it_refuses_an_impossible_pair(int $minimum, int $maximum): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StaffingDefaults($minimum, $maximum);
    }

    #[Test]
    public function it_accepts_a_maximum_equal_to_the_minimum(): void
    {
        $defaults = new StaffingDefaults(3, 3);

        $this->assertSame([3, 3], [$defaults->minimumWalkers, $defaults->maximumWalkers]);
    }
}
