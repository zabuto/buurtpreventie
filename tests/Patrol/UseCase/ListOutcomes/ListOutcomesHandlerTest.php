<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ListOutcomes;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\UseCase\ListOutcomes\ListOutcomes;
use App\Patrol\UseCase\ListOutcomes\ListOutcomesHandler;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListOutcomesHandlerTest extends TestCase
{
    private InMemoryOutcomeRepository $outcomes;

    #[Override]
    protected function setUp(): void
    {
        $this->outcomes = new InMemoryOutcomeRepository();
    }

    #[Test]
    public function it_lists_the_outcomes_to_choose_from_by_name_with_their_kind(): void
    {
        $rustig = $this->stored('Rustig', OutcomeKind::NothingToReport);
        $inbraak = $this->stored('Inbraak', OutcomeKind::Incident);

        $output = $this->handler()(new ListOutcomes());

        $this->assertSame(
            [
                ['id' => $inbraak->id()->toRfc4122(), 'name' => 'Inbraak', 'kind' => 'incident', 'archived' => false],
                ['id' => $rustig->id()->toRfc4122(), 'name' => 'Rustig', 'kind' => 'nothing_to_report', 'archived' => false],
            ],
            $output->outcomes,
        );
    }

    #[Test]
    public function it_leaves_archived_outcomes_out_of_the_choice(): void
    {
        $this->stored('Rustig', OutcomeKind::NothingToReport)->archive();

        $output = $this->handler()(new ListOutcomes());

        $this->assertSame([], $output->outcomes);
    }

    #[Test]
    public function it_lists_archived_outcomes_too_for_whoever_manages_them(): void
    {
        $this->stored('Rustig', OutcomeKind::NothingToReport)->archive();
        $authorization = FakeAuthorization::granting();

        $output = $this->handler($authorization)(new ListOutcomes(withArchived: true));

        $this->assertSame([['Rustig', true]], \array_map(static fn(array $outcome): array => [$outcome['name'], $outcome['archived']], $output->outcomes));
        $this->assertSame([['catalogue.manage', null]], $authorization->asked);
    }

    private function stored(string $name, OutcomeKind $kind): Outcome
    {
        $outcome = Outcome::create($name, $kind);
        $this->outcomes->add($outcome);

        return $outcome;
    }

    private function handler(?FakeAuthorization $authorization = null): ListOutcomesHandler
    {
        return new ListOutcomesHandler($this->outcomes, $authorization ?? FakeAuthorization::granting());
    }
}
