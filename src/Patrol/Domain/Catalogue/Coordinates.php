<?php

declare(strict_types=1);

namespace App\Patrol\Domain\Catalogue;

/**
 * A point in WGS84, rounded to the six decimals (about 10 cm) its DECIMAL(9,6) columns hold.
 */
final readonly class Coordinates
{
    public float $latitude;
    public float $longitude;

    /**
     * @throws InvalidCoordinates for a latitude beyond ±90 or a longitude beyond ±180
     */
    public function __construct(float $latitude, float $longitude)
    {
        if (\abs($latitude) > 90.0 || \abs($longitude) > 180.0) {
            throw new InvalidCoordinates(\sprintf('%F, %F is not a point on the globe.', $latitude, $longitude));
        }

        $this->latitude = \round($latitude, 6);
        $this->longitude = \round($longitude, 6);
    }

    /**
     * A meeting point has a position or none; half a position is a mistake in the input.
     *
     * @throws InvalidCoordinates for one coordinate without the other, or a point off the globe
     */
    public static function fromOptional(?float $latitude, ?float $longitude): ?self
    {
        if (null === $latitude && null === $longitude) {
            return null;
        }

        if (null === $latitude || null === $longitude) {
            throw new InvalidCoordinates('A meeting point takes both a latitude and a longitude, or neither.');
        }

        return new self($latitude, $longitude);
    }
}
