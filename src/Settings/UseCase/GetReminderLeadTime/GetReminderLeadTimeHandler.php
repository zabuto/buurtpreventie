<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetReminderLeadTime;

use App\Settings\Domain\SettingName;
use App\Settings\Domain\SettingRepository;
use App\Settings\Domain\Settings;
use App\Shared\Domain\ReadAction;

/**
 * How many days ahead the scheduler reminds a walker of their rounds. For other modules only; no entry point.
 */
#[ReadAction]
final readonly class GetReminderLeadTimeHandler
{
    public function __construct(
        private SettingRepository $settings,
    ) {
    }

    public function __invoke(GetReminderLeadTime $input): GetReminderLeadTimeOutput
    {
        return new GetReminderLeadTimeOutput((int) Settings::of($this->settings->all())->value(SettingName::ReminderDaysAhead));
    }
}
