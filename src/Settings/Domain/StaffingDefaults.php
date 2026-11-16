<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * The minimum and maximum walkers a new round gets. A round keeps the pair it was created with, so changing these
 * never rewrites the past.
 */
final readonly class StaffingDefaults
{
    public const string MINIMUM_SETTING = 'patrol.minimum_walkers';
    public const string MAXIMUM_SETTING = 'patrol.maximum_walkers';
    public const int MINIMUM = 2;
    public const int MAXIMUM = 7;

    /**
     * @throws InvalidStaffingDefaults  when the minimum is below one or the maximum below the minimum
     */
    public function __construct(
        public int $minimumWalkers,
        public int $maximumWalkers,
    ) {
        if ($minimumWalkers < 1 || $maximumWalkers < $minimumWalkers) {
            throw new InvalidStaffingDefaults(\sprintf(
                'A minimum of %d and a maximum of %d walkers cannot both hold.',
                $minimumWalkers,
                $maximumWalkers,
            ));
        }
    }
}
