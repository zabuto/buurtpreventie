<?php

declare(strict_types=1);

namespace App\Settings\UseCase\RemoveBrandImage;

use App\Settings\Domain\BrandImages;
use App\Settings\Domain\InvalidSetting;
use App\Settings\Domain\SettingName;
use App\Settings\Domain\SettingRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

/**
 * Puts the default logo or icon back. The files go once that is committed, so a reader always finds the files the
 * setting names.
 */
#[WriteAction(authorization: 'settings.change', audit: 'setting.image_changed')]
final readonly class RemoveBrandImageHandler
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
     */
    public function __invoke(RemoveBrandImage $input): void
    {
        $this->authorization->denyUnlessGranted('settings.change');

        $kind = $input->kind();
        $this->transaction->run(function () use ($kind): void {
            $name = $kind->setting()->value;
            $setting = $this->settings->find($name);
            if (null === $setting) {
                return;
            }

            $version = $setting->value();
            $this->settings->remove($setting);
            $this->auditTrail->record(new AuditedAction('setting.image_changed', 'settings', Uuid::fromString(SettingName::SUBJECT_ID), [
                $name => ['old' => $version, 'new' => null],
            ]));
            $this->transaction->afterCommit(fn() => $this->images->remove($kind, $version));
        });
    }
}
