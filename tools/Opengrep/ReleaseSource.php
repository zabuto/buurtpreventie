<?php

declare(strict_types=1);

namespace App\Tools\Opengrep;

interface ReleaseSource
{
    /**
     * @return list<Release>
     *
     * @throws \RuntimeException when the releases cannot be fetched or read
     */
    public function releases(): array;
}
