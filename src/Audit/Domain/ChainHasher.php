<?php

declare(strict_types=1);

namespace App\Audit\Domain;

/**
 * Signs the fields of an audit entry with a key that lives outside the database, so whoever can change the database
 * but not read that key cannot make a hash that fits.
 */
interface ChainHasher
{
    /**
     * @param list<scalar|null> $fields
     *
     * @return string 32 raw bytes
     */
    public function hash(array $fields): string;
}
