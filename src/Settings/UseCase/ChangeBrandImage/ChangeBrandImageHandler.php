<?php

declare(strict_types=1);

namespace App\Settings\UseCase\ChangeBrandImage;

use App\Settings\Domain\BrandImages;
use App\Settings\Domain\InvalidSetting;
use App\Settings\Domain\Setting;
use App\Settings\Domain\SettingName;
use App\Settings\Domain\SettingRepository;
use App\Settings\Domain\UnsupportedImage;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

/**
 * Replaces the logo or icon. The new files are stored before the setting points at them, and the previous files go
 * only once that change is committed, so a reader always finds the files the setting names. A rollback leaves the new
 * files unused; their names come from their content, so they never clash with another image.
 */
#[WriteAction(authorization: 'settings.change', audit: 'setting.image_changed')]
final readonly class ChangeBrandImageHandler
{
    public function __construct(
        private SettingRepository $settings,
        private BrandImages $images,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws InvalidSetting
     * @throws UnsupportedImage
     */
    public function __invoke(ChangeBrandImage $input): void
    {
        $this->authorization->denyUnlessGranted('settings.change');

        $kind = $input->kind();
        $version = $this->images->store($kind, $input->content);

        $this->transaction->run(function () use ($kind, $version): void {
            $name = $kind->setting()->value;
            $setting = $this->settings->find($name);
            $previous = $setting?->value();
            if ($previous === $version) {
                return;
            }

            null === $setting ? $this->settings->add(Setting::of($name, $version)) : $setting->change($version);
            $this->auditTrail->record(new AuditedAction('setting.image_changed', 'settings', Uuid::fromString(SettingName::SUBJECT_ID), [
                $name => ['old' => $previous, 'new' => $version],
            ]));

            if (null !== $previous) {
                $this->transaction->afterCommit(fn() => $this->images->remove($kind, $previous));
            }
        });
    }
}
