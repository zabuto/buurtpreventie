<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\GetStaffingDefaults;

use App\Settings\Domain\Setting;
use App\Settings\UseCase\GetStaffingDefaults\GetStaffingDefaults;
use App\Settings\UseCase\GetStaffingDefaults\GetStaffingDefaultsHandler;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetStaffingDefaultsHandlerTest extends TestCase
{
    #[Test]
    public function it_gives_two_and_seven_walkers_when_nothing_is_set(): void
    {
        $output = new GetStaffingDefaultsHandler(new InMemorySettingRepository())(new GetStaffingDefaults());

        $this->assertSame([2, 7], [$output->minimumWalkers, $output->maximumWalkers]);
    }

    #[Test]
    public function it_gives_the_stored_minimum_and_maximum(): void
    {
        $settings = new InMemorySettingRepository();
        $settings->add(Setting::of('patrol.minimum_walkers', '3'));
        $settings->add(Setting::of('patrol.maximum_walkers', '5'));

        $output = new GetStaffingDefaultsHandler($settings)(new GetStaffingDefaults());

        $this->assertSame([3, 5], [$output->minimumWalkers, $output->maximumWalkers]);
    }
}
