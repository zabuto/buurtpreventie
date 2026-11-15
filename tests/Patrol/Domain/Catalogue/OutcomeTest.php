<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain\Catalogue;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OutcomeTest extends TestCase
{
    #[Test]
    public function it_keys_an_outcome_by_its_name(): void
    {
        $outcome = Outcome::create('Café open', OutcomeKind::Remarks);

        $this->assertSame('cafe open', $outcome->nameKey());
    }

    #[Test]
    public function it_keys_a_renamed_outcome_by_its_new_name(): void
    {
        $outcome = Outcome::create('Café open', OutcomeKind::Remarks);

        $outcome->rename('Hek open');

        $this->assertSame(['Hek open', 'hek open'], [$outcome->name(), $outcome->nameKey()]);
    }

    #[Test]
    public function it_restores_an_archived_outcome(): void
    {
        $outcome = Outcome::create('Café open', OutcomeKind::Remarks);
        $outcome->archive();

        $outcome->restore();

        $this->assertFalse($outcome->isArchived());
    }
}
