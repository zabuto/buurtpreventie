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

    #[Test]
    public function it_allows_every_role_on_mcp_by_default(): void
    {
        $output = new GetMcpAccessHandler(new InMemorySettingRepository())(new GetMcpAccess());

        $this->assertSame(['admin', 'analyst', 'coordinator', 'walker'], $output->roles);
    }

    #[Test]
    public function it_allows_only_the_roles_the_admin_chose_on_mcp(): void
    {
        $settings = new InMemorySettingRepository();
        $settings->add(Setting::of('integrations.mcp_roles', 'analyst,coordinator'));

        $output = new GetMcpAccessHandler($settings)(new GetMcpAccess());

        $this->assertSame(['analyst', 'coordinator'], $output->roles);
    }

    #[Test]
    public function it_allows_no_role_on_mcp_when_the_admin_picks_members_only(): void
    {
        $settings = new InMemorySettingRepository();
        $settings->add(Setting::of('integrations.mcp_roles', ''));

        $output = new GetMcpAccessHandler($settings)(new GetMcpAccess());

        $this->assertSame([], $output->roles);
    }
}
