<?php

namespace App\Geo\ValueObject;

/**
 * Point
 */
class Point
{
    /**
     * @var float
     */
    private $latitude;

    /**
     * @var float
     */
    private $longitude;

    /**
     * Point
     *
     * @param  string $latlng
     * @return Point
     */
    public static function latlng($latlng)
    {
        $list = explode(',', $latlng);

        return new static($list[0], $list[1]);
    }

    /**
     * Constructor
     *
     * @param  float $latitude
     * @param  float $longitude
     */
    public function __construct($latitude, $longitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * @return string
     */
    public function getLatLng()
    {
        return $this->latitude . ',' . $this->longitude;
    }

    /**
     * @return float
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * @return float
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Magic method toString
     *
     * @return string
     */
    public function __toString()
    {
        return sprintf('POINT(%f %f)', $this->longitude, $this->latitude);
    }
}
