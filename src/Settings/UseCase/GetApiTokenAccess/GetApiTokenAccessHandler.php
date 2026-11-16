<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetApiTokenAccess;

use App\Settings\Domain\SettingName;
use App\Settings\Domain\SettingRepository;
use App\Settings\Domain\Settings;
use App\Shared\Domain\ReadAction;

/**
 * Whether the association lets API tokens in. Read on every request with a token, before anyone is authenticated,
 * so it asks no voter. For other modules only; no entry point.
 */
#[ReadAction]
final readonly class GetApiTokenAccessHandler
{
    public function __construct(
        private SettingRepository $settings,
    ) {
    }

    public function __invoke(GetApiTokenAccess $input): GetApiTokenAccessOutput
    {
        return new GetApiTokenAccessOutput('1' === Settings::of($this->settings->all())->value(SettingName::ApiAccess));
    }
}
