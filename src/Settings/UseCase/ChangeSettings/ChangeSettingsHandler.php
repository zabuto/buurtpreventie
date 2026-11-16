<?php

declare(strict_types=1);

namespace App\Settings\UseCase\ChangeSettings;

use App\Settings\Domain\InsufficientContrast;
use App\Settings\Domain\InvalidSetting;
use App\Settings\Domain\InvalidStaffingDefaults;
use App\Settings\Domain\Setting;
use App\Settings\Domain\SettingName;
use App\Settings\Domain\SettingRepository;
use App\Settings\Domain\Settings;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Symfony\Component\Uid\Uuid;

/**
 * Changes the settings as one set, so a refused value leaves every other value of the patch unstored too. One audit
 * entry names what changed.
 */
#[WriteAction(authorization: 'settings.change', audit: 'setting.changed')]
final readonly class ChangeSettingsHandler
{
    public function __construct(
        private SettingRepository $settings,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws InvalidSetting
     * @throws InvalidStaffingDefaults
     * @throws InsufficientContrast
     */
    public function __invoke(ChangeSettings $input): void
    {
        $this->authorization->denyUnlessGranted('settings.change');

        $this->transaction->run(function () use ($input): void {
            $rows = [];
            foreach ($this->settings->all() as $setting) {
                $rows[$setting->name()] = $setting;
            }

            $before = Settings::of(\array_values($rows));
            $after = $before->with($input->changes());

            $this->store($rows, $after);
            $this->record($before, $after);
        });
    }

    /**
     * @param array<string, Setting> $rows the stored settings by name
     */
    private function store(array $rows, Settings $after): void
    {
        $values = $after->stored();
        foreach (SettingName::cases() as $name) {
            $row = $rows[$name->value] ?? null;
            $value = $values[$name->value] ?? null;

            match (true) {
                null === $value && null !== $row => $this->settings->remove($row),
                null === $value => null,
                null === $row => $this->settings->add(Setting::of($name->value, $value)),
                default => $row->change($value),
            };
        }
    }

    private function record(Settings $before, Settings $after): void
    {
        $changes = [];
        foreach ($after->differencesFrom($before) as $name) {
            $changes[$name->value] = $name->isAuditedWithValue()
                ? ['old' => $before->value($name), 'new' => $after->value($name)]
                : ['old' => null, 'new' => null];
        }

        if ([] === $changes) {
            return;
        }

        $this->auditTrail->record(new AuditedAction('setting.changed', 'settings', Uuid::fromString(SettingName::SUBJECT_ID), $changes));
    }
}
