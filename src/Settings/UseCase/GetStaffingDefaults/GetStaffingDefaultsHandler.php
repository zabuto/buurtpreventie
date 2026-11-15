<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetStaffingDefaults;

use App\Settings\Domain\SettingRepository;
use App\Settings\Domain\StaffingDefaults;
use App\Shared\Domain\ReadAction;

/**
 * The staffing rule for a new round: the stored minimum and maximum, or two and seven when none is stored. Public,
 * like the rest of the configuration the frontend reads.
 */
#[ReadAction]
final readonly class GetStaffingDefaultsHandler
{
    public function __construct(
        private SettingRepository $settings,
    ) {
    }

    public function __invoke(GetStaffingDefaults $input): GetStaffingDefaultsOutput
    {
        $defaults = new StaffingDefaults(
            $this->number(StaffingDefaults::MINIMUM_SETTING, StaffingDefaults::MINIMUM),
            $this->number(StaffingDefaults::MAXIMUM_SETTING, StaffingDefaults::MAXIMUM),
        );

        return new GetStaffingDefaultsOutput($defaults->minimumWalkers, $defaults->maximumWalkers);
    }

    private function number(string $name, int $default): int
    {
        $value = $this->settings->find($name)?->value();

        return null === $value ? $default : (int) $value;
    }
}
