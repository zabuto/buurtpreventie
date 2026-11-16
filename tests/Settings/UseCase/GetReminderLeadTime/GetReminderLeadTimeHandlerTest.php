<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\GetReminderLeadTime;

use App\Settings\Domain\Setting;
use App\Settings\UseCase\GetReminderLeadTime\GetReminderLeadTime;
use App\Settings\UseCase\GetReminderLeadTime\GetReminderLeadTimeHandler;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetReminderLeadTimeHandlerTest extends TestCase
{
    #[Test]
    public function it_gives_the_days_ahead_the_association_set(): void
    {
        $settings = new InMemorySettingRepository();
        $settings->add(Setting::of('patrol.reminder_days_ahead', '3'));

        $output = new GetReminderLeadTimeHandler($settings)(new GetReminderLeadTime());

        $this->assertSame(3, $output->daysAhead);
    }

    #[Test]
    public function it_reminds_one_day_ahead_by_default(): void
    {
        $output = new GetReminderLeadTimeHandler(new InMemorySettingRepository())(new GetReminderLeadTime());

        $this->assertSame(1, $output->daysAhead);
    }
}
