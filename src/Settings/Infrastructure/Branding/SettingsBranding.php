<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Branding;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\BrandImages;
use App\Settings\Domain\SettingName;
use App\Settings\Domain\SettingRepository;
use App\Settings\Domain\Settings;
use App\Shared\Domain\Branding;
use Override;

/**
 * Reads the settings afresh on every call, so a mail always carries the branding of the moment it leaves.
 */
final readonly class SettingsBranding implements Branding
{
    private const int MAIL_LOGO_SIZE = 128;

    public function __construct(
        private SettingRepository $settings,
        private BrandImages $images,
    ) {
    }

    #[Override]
    public function associationName(): string
    {
        return (string) $this->settings()->value(SettingName::AssociationName);
    }

    #[Override]
    public function senderName(): string
    {
        return $this->settings()->value(SettingName::MailSenderName) ?? $this->associationName();
    }

    #[Override]
    public function primaryColor(): string
    {
        return (string) $this->settings()->value(SettingName::PrimaryColor);
    }

    #[Override]
    public function language(): string
    {
        return (string) $this->settings()->value(SettingName::Language);
    }

    #[Override]
    public function mailLogo(): ?string
    {
        $version = $this->settings()->value(SettingName::Logo);

        return null === $version ? null : $this->images->content(BrandImageFile::of(BrandImageKind::Logo, self::MAIL_LOGO_SIZE, $version));
    }

    #[Override]
    public function contactEmail(): ?string
    {
        return $this->settings()->value(SettingName::ContactEmail);
    }

    private function settings(): Settings
    {
        return Settings::of($this->settings->all());
    }
}
