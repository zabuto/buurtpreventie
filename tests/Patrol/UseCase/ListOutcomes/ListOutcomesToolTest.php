<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListOutcomes;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Support\McpTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class ListOutcomesToolTest extends McpTestCase
{
    #[Test]
    public function it_gives_the_same_outcomes_as_the_rest_endpoint(): void
    {
        $browser = self::mcpBrowser();
        self::token(self::member());
        self::service(InMemoryOutcomeRepository::class)->add(Outcome::create('Niets bijzonders', OutcomeKind::NothingToReport));
        self::service(InMemoryOutcomeRepository::class)->add(Outcome::create('Inbraak', OutcomeKind::Incident));

        $listed = self::call(self::connect($browser), 'list_outcomes');

        self::assertCount(2, (array) ($listed['outcomes'] ?? []));
        self::assertSame(self::rest($browser, '/api/v1/outcomes'), $listed);
    }
}
