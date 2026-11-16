<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

/**
 * The made-up password of every sample member, so a developer logs in as any role. The sample data exist only in
 * dev and test, never on a server with real members.
 */
final readonly class SamplePassword
{
    public const string VALUE = 'voorbeeld-wachtwoord-buurt';
}
