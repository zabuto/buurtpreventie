<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use DateTimeImmutable;
use Override;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * The least an installation needs: an admin, a meeting point and the standard outcomes. Each test builds the rest.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final readonly class TestSet implements FixtureSet
{
    public function __construct(
        private MemberSteps $members,
        private PatrolSteps $patrol,
    ) {
    }

    #[Override]
    public function load(DateTimeImmutable $now): void
    {
        $at = $now->modify('-1 day');
        $admin = $this->members->firstAdmin($at, new SampleProfile('beheer@example.org', 'Bea Beheer', 'Bea', '06 0000 0001'));
        $this->patrol->meetingPoint($admin, $at, 'Buurthuis');
        $this->patrol->standardOutcomes($admin, $at);
    }
}
