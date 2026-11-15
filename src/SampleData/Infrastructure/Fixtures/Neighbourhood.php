<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

/**
 * Who walks in the demo and what they choose from.
 */
final readonly class Neighbourhood
{
    /**
     * @param list<SampleMember> $walkers
     * @param non-empty-list<string> $meetingPoints ids
     * @param array{nothing_to_report: string, remarks: string, incident: string} $outcomes ids by kind
     */
    public function __construct(
        public SampleMember $coordinator,
        public array $walkers,
        public array $meetingPoints,
        public array $outcomes,
    ) {
    }
}
