<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain\Catalogue;

use App\Patrol\Domain\Catalogue\Coordinates;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CoordinatesTest extends TestCase
{
    /**
     * @return iterable<string, array{float, float}>
     */
    public static function outOfRange(): iterable
    {
        yield 'latitude above 90' => [90.000_001, 5.0];
        yield 'latitude below -90' => [-90.000_001, 5.0];
        yield 'longitude above 180' => [52.0, 180.000_001];
        yield 'longitude below -180' => [52.0, -180.000_001];
    }

    #[DataProvider('outOfRange')]
    #[Test]
    public function it_refuses_a_point_outside_the_globe(float $latitude, float $longitude): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Coordinates($latitude, $longitude);
    }

    #[Test]
    public function it_accepts_the_edges_of_the_globe(): void
    {
        $coordinates = new Coordinates(-90.0, 180.0);

        $this->assertSame([-90.0, 180.0], [$coordinates->latitude, $coordinates->longitude]);
    }

    #[Test]
    public function it_keeps_six_decimals_as_the_column_does(): void
    {
        $coordinates = new Coordinates(52.370_214_49, 4.895_168_49);

        $this->assertSame([52.370_214, 4.895_168], [$coordinates->latitude, $coordinates->longitude]);
    }

    #[Test]
    public function it_takes_no_position_from_two_missing_coordinates(): void
    {
        $this->assertNull(Coordinates::fromOptional(null, null));
    }

    #[Test]
    public function it_takes_a_position_from_both_coordinates(): void
    {
        $coordinates = Coordinates::fromOptional(52.1, 5.1);

        $this->assertSame([52.1, 5.1], [$coordinates?->latitude, $coordinates?->longitude]);
    }

    /**
     * @return iterable<string, array{float|null, float|null}>
     */
    public static function halfAPosition(): iterable
    {
        yield 'latitude only' => [52.1, null];
        yield 'longitude only' => [null, 5.1];
    }

    #[DataProvider('halfAPosition')]
    #[Test]
    public function it_refuses_one_coordinate_without_the_other(?float $latitude, ?float $longitude): void
    {
        $this->expectException(InvalidArgumentException::class);

        Coordinates::fromOptional($latitude, $longitude);
    }
}
