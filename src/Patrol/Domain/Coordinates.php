<?php

declare(strict_types=1);

namespace App\Patrol\Domain;

use InvalidArgumentException;

/**
 * A point in WGS84, rounded to the six decimals (about 10 cm) its DECIMAL(9,6) columns hold.
 */
final readonly class Coordinates
{
    public float $latitude;
    public float $longitude;

    /**
     * @throws InvalidArgumentException for a latitude beyond ±90 or a longitude beyond ±180
     */
    public function __construct(float $latitude, float $longitude)
    {
        if (\abs($latitude) > 90.0 || \abs($longitude) > 180.0) {
            throw new InvalidArgumentException(\sprintf('%F, %F is not a point on the globe.', $latitude, $longitude));
        }

        $this->latitude = \round($latitude, 6);
        $this->longitude = \round($longitude, 6);
    }
}
