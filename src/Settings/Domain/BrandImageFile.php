<?php

declare(strict_types=1);

namespace App\Settings\Domain;

/**
 * One stored size of a logo or icon, named `<kind>-<size>-<version>.png`. The version comes from the uploaded content,
 * so a new image gets new names and a browser may cache each file for good. Only a name that fits this pattern
 * becomes a file, so no request value ever reaches a path.
 */
final readonly class BrandImageFile
{
    private const string NAME = '/^(logo|icon)-(\d{1,4})-([0-9a-f]{16})\.png$/D';
    private const string VERSION = '/^[0-9a-f]{16}$/D';

    private function __construct(
        public BrandImageKind $kind,
        public int $size,
        public string $version,
    ) {
    }

    public static function of(BrandImageKind $kind, int $size, string $version): self
    {
        \assert(self::isVersion($version), 'A version comes from versionOf().');

        return new self($kind, $size, $version);
    }

    public static function fromName(string $name): ?self
    {
        if (1 !== \preg_match(self::NAME, $name, $parts)) {
            return null;
        }

        $kind = BrandImageKind::from($parts[1]);
        $size = (int) $parts[2];
        $sizes = \array_map(static fn(Rendition $rendition): int => $rendition->size, $kind->renditions());

        return \in_array($size, $sizes, strict: true) ? new self($kind, $size, $parts[3]) : null;
    }

    public static function versionOf(string $content): string
    {
        return \substr(\hash('sha256', $content), 0, 16);
    }

    public static function isVersion(string $value): bool
    {
        return 1 === \preg_match(self::VERSION, $value);
    }

    /**
     * A setting of an image holds the version in its file names; only an upload sets it.
     *
     * @throws InvalidSetting
     */
    public static function checkedVersion(SettingName $setting, string $value): string
    {
        return self::isVersion($value)
            ? $value
            : throw new InvalidSetting($setting->value, \sprintf('%s is the version of an uploaded image.', $setting->value));
    }

    public function name(): string
    {
        return \sprintf('%s-%d-%s.png', $this->kind->value, $this->size, $this->version);
    }
}
