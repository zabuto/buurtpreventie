<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetWebManifest;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\BrandPalette;
use App\Settings\Domain\SettingName;
use App\Settings\Domain\SettingRepository;
use App\Settings\Domain\Settings;
use App\Shared\Domain\AppUrl;
use App\Shared\Domain\ReadAction;

/**
 * Lets a phone install the app under the name, colour and icon of the association. Public, as a browser reads it
 * before anyone logs in.
 */
#[ReadAction]
final readonly class GetWebManifestHandler
{
    /** The sizes Chrome and Android need to offer the install. */
    private const array ICON_SIZES = [192, 512];

    public function __construct(
        private SettingRepository $settings,
        private AppUrl $appUrl,
    ) {
    }

    public function __invoke(GetWebManifest $input): GetWebManifestOutput
    {
        $settings = Settings::of($this->settings->all());
        $version = $settings->value(SettingName::Icon) ?? (string) BrandImageKind::Icon->defaultVersion();
        $icons = [];
        foreach (self::ICON_SIZES as $size) {
            $icons[] = [
                'src' => $this->appUrl->pathTo('api/v1/media/brand/' . BrandImageFile::of(BrandImageKind::Icon, $size, $version)->name()),
                'sizes' => $size . 'x' . $size,
                'type' => 'image/png',
            ];
        }

        return new GetWebManifestOutput(
            name: (string) $settings->value(SettingName::AssociationName),
            shortName: (string) $settings->value(SettingName::AssociationShortName),
            language: (string) $settings->value(SettingName::Language),
            startUrl: $this->appUrl->pathTo('app/'),
            scope: $this->appUrl->pathTo('app/'),
            display: 'standalone',
            themeColor: (string) $settings->value(SettingName::PrimaryColor),
            backgroundColor: BrandPalette::LIGHT_BACKGROUND,
            icons: $icons,
        );
    }
}
