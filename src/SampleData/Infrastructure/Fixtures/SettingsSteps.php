<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use App\Settings\UseCase\ChangeBrandImage\ChangeBrandImage;
use App\Settings\UseCase\ChangeBrandImage\ChangeBrandImageHandler;
use App\Settings\UseCase\ChangeSettings\ChangeSettings;
use App\Settings\UseCase\ChangeSettings\ChangeSettingsHandler;
use App\Shared\Domain\Channel;
use App\Shared\Infrastructure\Security\RunAs;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * The branding of an association through the settings and uploads the admin uses, so a set proves that a second
 * association needs no change to the code.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final readonly class SettingsSteps
{
    public function __construct(
        private Timeline $timeline,
        private RunAs $runAs,
        private ChangeSettingsHandler $changeSettings,
        private ChangeBrandImageHandler $changeBrandImage,
    ) {
    }

    public function change(SampleMember $admin, DateTimeImmutable $at, ChangeSettings $settings): void
    {
        $this->as($admin, $at, fn() => ($this->changeSettings)($settings));
    }

    /**
     * @param 'logo'|'icon' $kind
     */
    public function upload(SampleMember $admin, DateTimeImmutable $at, string $kind, string $file): void
    {
        $this->as($admin, $at, fn() => ($this->changeBrandImage)(new ChangeBrandImage($kind, $file)));
    }

    private function as(SampleMember $admin, DateTimeImmutable $at, callable $step): void
    {
        $this->timeline->at($at, fn(): mixed => $this->runAs->member($admin->actor, Channel::Console, $step));
    }
}
