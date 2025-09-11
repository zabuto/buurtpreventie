<?php declare(strict_types=1);

namespace App\Geo\ValueObject;

final class Point
{
    private float $latitude;
    private float $longitude;

    public static function latlng(string $latlng): Point
    {
        $list = explode(',', $latlng);

        return new static((float)$list[0], (float)$list[1]);
    }

    public function __construct(float $latitude, float $longitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function getLatLng(): string
    {
        return $this->latitude . ',' . $this->longitude;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function __toString(): string
    {
        return sprintf('POINT(%f %f)', $this->longitude, $this->latitude);
    }
}
