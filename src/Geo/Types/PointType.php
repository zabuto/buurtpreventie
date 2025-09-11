<?php declare(strict_types=1);

namespace App\Geo\Types;

use App\Geo\ValueObject\Point;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Point Mapping Type
 */
class PointType extends Type
{
    public const string POINT = 'point';

    public function getName(): string
    {
        return self::POINT;
    }

    /**
     * {@inheritDoc}
     */
    public function getSqlDeclaration(array $column, AbstractPlatform $platform): string
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

        [$longitude, $latitude] = sscanf($value, "POINT(%f %f)");
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
    public function canRequireSQLConversion(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform): string
    {
        return 'ST_PointFromText(' . $sqlExpr . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function convertToPHPValueSQL($sqlExpr, $platform): string
    {
        return sprintf('ST_AsText(%s)', $sqlExpr);
    }

    /**
     * {@inheritDoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
