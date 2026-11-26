<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetMcpAccess;

use App\Settings\Domain\RoleList;
use App\Settings\Domain\SettingName;
use App\Settings\Domain\SettingRepository;
use App\Settings\Domain\Settings;
use App\Shared\Domain\ReadAction;

/**
 * Whether the association opens its MCP server, and to which roles. Read on every request to it, before anyone is
 * authenticated, so it asks no voter. For other modules only; no entry point.
 */
#[ReadAction]
final readonly class GetMcpAccessHandler
{
    public function __construct(
        private SettingRepository $settings,
    ) {
    }

    public function __invoke(GetMcpAccess $input): GetMcpAccessOutput
    {
        $settings = Settings::of($this->settings->all());

        return new GetMcpAccessOutput(
            '1' === $settings->value(SettingName::McpAccess),
            RoleList::of((string) $settings->value(SettingName::McpRoles)),
        );
    }
}
