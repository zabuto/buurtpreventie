<?php

declare(strict_types=1);

namespace App\Tests\Identity\Fake;

use App\Identity\Domain\CurrentSession;
use Override;
use Symfony\Component\Uid\Uuid;

final class RecordingCurrentSession implements CurrentSession
{
    /** @var list<Uuid> */
    public array $kept = [];

    #[Override]
    public function keepAfterNewPassword(Uuid $memberId): void
    {
        $this->kept[] = $memberId;
    }
}
