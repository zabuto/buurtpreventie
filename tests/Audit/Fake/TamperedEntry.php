<?php

declare(strict_types=1);

namespace App\Tests\Audit\Fake;

use App\Audit\Domain\AuditEntry;
use ReflectionClass;

/**
 * A copy of an entry with some stored values replaced, as someone with access to the database could leave it. The
 * entity offers no way to change these values, so the copy is made through reflection.
 */
final class TamperedEntry
{
    /**
     * @param array<string, mixed> $replacements property name => stored value
     */
    public static function of(AuditEntry $entry, array $replacements): AuditEntry
    {
        $class = new ReflectionClass(AuditEntry::class);
        $copy = $class->newInstanceWithoutConstructor();
        foreach ($class->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();
            $property->setValue($copy, \array_key_exists($name, $replacements) ? $replacements[$name] : $property->getValue($entry));
        }

        return $copy;
    }
}
