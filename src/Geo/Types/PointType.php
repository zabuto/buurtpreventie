<?php

namespace App\Geo\Types;

use App\Geo\ValueObject\Point;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Doctrine Point Mapping Type
 */
class PointType extends Type
{
    /** @var string */
    const POINT = 'point';

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return self::POINT;
    }

    /**
     * {@inheritDoc}
     */
    public function getSqlDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'POINT';
    }

    /**
     * {@inheritDoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if (null === $value) {
            return null;
        }

        list($longitude, $latitude) = sscanf($value, "POINT(%f %f)");
        if ($longitude === 0.0 && $latitude === 0.0) {
            return null;
        }

        return new Point($latitude, $longitude);
    }

    /**
     * {@inheritDoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value instanceof Point) {
            if (empty($value->getLongitude()) && empty($value->getLatitude())) {
                return null;
            }

            return sprintf('POINT(%f %f)', $value->getLongitude(), $value->getLatitude());
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function canRequireSQLConversion()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
    {
        return 'PointFromText(' . $sqlExpr . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function convertToPHPValueSQL($sqlExpr, $platform)
    {
        return sprintf('AsText(%s)', $sqlExpr);
    }
}
