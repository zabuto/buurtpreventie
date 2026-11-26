<?php

declare(strict_types=1);

namespace App\Tests\Dependencies;

/**
 * Reads the parts of composer.json and composer.lock that dependency tests check, narrowed to plain strings.
 */
final readonly class ComposerFiles
{
    public function __construct(
        private string $projectRoot,
    ) {
    }

    public function symfonyReleaseLine(): ?string
    {
        $line = $this->value($this->read('composer.json'), 'extra', 'symfony', 'require');

        return \is_string($line) ? $line : null;
    }

    /**
     * @return array<string, string> package => constraint, from require and require-dev
     */
    public function requirements(): array
    {
        $composer = $this->read('composer.json');
        $requirements = [];
        foreach (['require', 'require-dev'] as $section) {
            foreach ($this->list($composer, $section) as $package => $constraint) {
                if (!\is_string($package) || !\is_string($constraint)) {
                    continue;
                }

                $requirements[$package] = $constraint;
            }
        }

        return $requirements;
    }

    /**
     * @return array<string, string> package => constraint, from conflict
     */
    public function conflicts(): array
    {
        $conflicts = [];
        foreach ($this->list($this->read('composer.json'), 'conflict') as $package => $constraint) {
            if (!\is_string($package) || !\is_string($constraint)) {
                continue;
            }

            $conflicts[$package] = $constraint;
        }

        return $conflicts;
    }

    /**
     * @return array<string, string> package => locked version, from packages and packages-dev
     */
    public function lockedVersions(): array
    {
        $lock = $this->read('composer.lock');
        $versions = [];
        foreach ([...$this->list($lock, 'packages'), ...$this->list($lock, 'packages-dev')] as $package) {
            $name = $this->value($package, 'name');
            $version = $this->value($package, 'version');
            if (\is_string($name) && \is_string($version)) {
                $versions[$name] = $version;
            }
        }

        return $versions;
    }

    /**
     * @return array<mixed>
     */
    private function list(mixed $data, string $key): array
    {
        $list = $this->value($data, $key);

        return \is_array($list) ? $list : [];
    }

    /**
     * Follows the keys into nested arrays; null when a level is missing or not an array.
     */
    private function value(mixed $data, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (!\is_array($data)) {
                return null;
            }

            $data = $data[$key] ?? null;
        }

        return $data;
    }

    private function read(string $file): mixed
    {
        return \json_decode((string) \file_get_contents($this->projectRoot . '/' . $file), true);
    }
}
