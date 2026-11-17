<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\GetMcpAccess;

use App\Settings\Domain\Setting;
use App\Settings\UseCase\GetMcpAccess\GetMcpAccess;
use App\Settings\UseCase\GetMcpAccess\GetMcpAccessHandler;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetMcpAccessHandlerTest extends TestCase
{
    #[Test]
    public function it_keeps_mcp_off_by_default(): void
    {
        $output = new GetMcpAccessHandler(new InMemorySettingRepository())(new GetMcpAccess());

        $this->assertFalse($output->allowed);
    }

    #[Test]
    public function it_allows_mcp_once_the_admin_switched_it_on(): void
    {
        $settings = new InMemorySettingRepository();
        $settings->add(Setting::of('integrations.mcp', '1'));

        $output = new GetMcpAccessHandler($settings)(new GetMcpAccess());

        $this->assertTrue($output->allowed);
    }
}
