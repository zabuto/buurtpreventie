<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\GetApiTokenAccess;

use App\Settings\Domain\Setting;
use App\Settings\UseCase\GetApiTokenAccess\GetApiTokenAccess;
use App\Settings\UseCase\GetApiTokenAccess\GetApiTokenAccessHandler;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetApiTokenAccessHandlerTest extends TestCase
{
    #[Test]
    public function it_allows_api_tokens_by_default(): void
    {
        $output = new GetApiTokenAccessHandler(new InMemorySettingRepository())(new GetApiTokenAccess());

        $this->assertTrue($output->allowed);
    }

    #[Test]
    public function it_refuses_api_tokens_once_the_admin_switched_them_off(): void
    {
        $settings = new InMemorySettingRepository();
        $settings->add(Setting::of('integrations.api_access', '0'));

        $output = new GetApiTokenAccessHandler($settings)(new GetApiTokenAccess());

        $this->assertFalse($output->allowed);
    }
}
