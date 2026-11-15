<?php

declare(strict_types=1);

namespace App\Tests\Shared\Fake;

use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Authorization;
use Override;

/**
 * Grants or refuses every action, and records what it was asked. Who may do what is the voters' concern and has
 * its own matrix test.
 */
final class FakeAuthorization implements Authorization
{
    /** @var list<array{string, object|null}> */
    public array $asked = [];

    private function __construct(
        private readonly bool $grants,
    ) {
    }

    public static function granting(): self
    {
        return new self(grants: true);
    }

    public static function refusing(): self
    {
        return new self(grants: false);
    }

    #[Override]
    public function denyUnlessGranted(string $action, ?object $subject = null): void
    {
        $this->asked[] = [$action, $subject];

        if (!$this->grants) {
            throw AccessDenied::forAction($action);
        }
    }
}
