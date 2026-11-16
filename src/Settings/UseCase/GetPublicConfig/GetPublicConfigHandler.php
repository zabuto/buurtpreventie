<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetPublicConfig;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\GroupLink;
use App\Settings\Domain\Rendition;
use App\Settings\Domain\SettingName;
use App\Settings\Domain\SettingRepository;
use App\Settings\Domain\Settings;
use App\Settings\Domain\StandardTexts;
use App\Shared\Domain\AppUrl;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\ReadAction;

/**
 * What the frontend needs to look and speak like the association, for anyone, logged in or not. The contact of the
 * coordinator is personal data, and the group invitation lets anyone in to see the phone numbers of its members, so a
 * visitor gets both with the same keys and null.
 */
#[ReadAction]
final readonly class GetPublicConfigHandler
{
    public function __construct(
        private SettingRepository $settings,
        private StandardTexts $standardTexts,
        private ExecutionContext $context,
        private AppUrl $appUrl,
    ) {
    }

    public function __invoke(GetPublicConfig $input): GetPublicConfigOutput
    {
        $settings = Settings::of($this->settings->all());
        $language = (string) $settings->value(SettingName::Language);
        $member = null !== $this->context->actor();
        $group = $member ? $settings->value(SettingName::GroupUrl) : null;

        return new GetPublicConfigOutput(
            associationName: (string) $settings->value(SettingName::AssociationName),
            associationShortName: (string) $settings->value(SettingName::AssociationShortName),
            primaryColor: (string) $settings->value(SettingName::PrimaryColor),
            accentColor: (string) $settings->value(SettingName::AccentColor),
            primaryDarkColor: (string) $settings->value(SettingName::PrimaryDarkColor),
            accentDarkColor: (string) $settings->value(SettingName::AccentDarkColor),
            logoUrl: $this->logoUrl($settings),
            icons: $this->icons($settings),
            language: $language,
            welcomeText: $this->text($settings, 'en' === $language ? SettingName::WelcomeTextEn : SettingName::WelcomeTextNl),
            emergencyText: $this->text($settings, 'en' === $language ? SettingName::EmergencyTextEn : SettingName::EmergencyTextNl),
            privacyUrl: $settings->value(SettingName::PrivacyUrl),
            accessibilityUrl: $settings->value(SettingName::AccessibilityUrl),
            minimumWalkers: (int) $settings->value(SettingName::MinimumWalkers),
            maximumWalkers: (int) $settings->value(SettingName::MaximumWalkers),
            contact: [
                'name' => $member ? $settings->value(SettingName::ContactName) : null,
                'email' => $member ? $settings->value(SettingName::ContactEmail) : null,
                'phone' => $member ? $settings->value(SettingName::ContactPhone) : null,
            ],
            group: [
                'url' => $group,
                'service' => null === $group ? null : GroupLink::fromString($group)->service->value,
            ],
        );
    }

    private function text(Settings $settings, SettingName $name): string
    {
        return $settings->value($name) ?? $this->standardTexts->of($name);
    }

    private function logoUrl(Settings $settings): ?string
    {
        $version = $settings->value(SettingName::Logo);

        return null === $version ? null : $this->url(BrandImageFile::of(BrandImageKind::Logo, 512, $version));
    }

    /**
     * @return list<array{size: int, url: string}>
     */
    private function icons(Settings $settings): array
    {
        $version = $settings->value(SettingName::Icon) ?? (string) BrandImageKind::Icon->defaultVersion();

        return \array_map(
            fn(Rendition $rendition): array => [
                'size' => $rendition->size,
                'url' => $this->url(BrandImageFile::of(BrandImageKind::Icon, $rendition->size, $version)),
            ],
            BrandImageKind::Icon->renditions(),
        );
    }

    private function url(BrandImageFile $file): string
    {
        return $this->appUrl->pathTo('api/v1/media/brand/' . $file->name());
    }
}
