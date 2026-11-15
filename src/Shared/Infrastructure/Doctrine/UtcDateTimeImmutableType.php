<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Override;

/**
 * Stores an instant in a DATETIME column as its UTC clock time and reads it back as UTC. The standard type uses the
 * time zone of PHP in both directions, so the stored value would depend on the server configuration.
 *
 * Registered as utc_datetime_immutable.
 */
final class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone(new DateTimeZone('UTC'))->format($platform->getDateTimeFormatString());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::class, ['null', 'string']);
        }

        $dateTime = DateTimeImmutable::createFromFormat($platform->getDateTimeFormatString(), $value, new DateTimeZone('UTC'));
        if (false === $dateTime) {
            throw InvalidFormat::new($value, self::class, $platform->getDateTimeFormatString());
        }

        return $dateTime;
    }
}
