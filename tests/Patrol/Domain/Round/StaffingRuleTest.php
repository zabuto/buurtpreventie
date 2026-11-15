<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain\Round;

use App\Patrol\Domain\Round\StaffingRule;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StaffingRuleTest extends TestCase
{
    #[Test]
    public function it_refuses_a_maximum_below_the_minimum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StaffingRule(minimumWalkers: 3, maximumWalkers: 2);
    }

    #[Test]
    public function it_accepts_a_maximum_equal_to_the_minimum(): void
    {
        $rule = new StaffingRule(minimumWalkers: 3, maximumWalkers: 3);

        $this->assertSame([3, 3], [$rule->minimumWalkers, $rule->maximumWalkers]);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function minimumsBelowOne(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('minimumsBelowOne')]
    #[Test]
    public function it_refuses_a_minimum_below_one(int $minimum): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StaffingRule(minimumWalkers: $minimum, maximumWalkers: 7);
    }
}
