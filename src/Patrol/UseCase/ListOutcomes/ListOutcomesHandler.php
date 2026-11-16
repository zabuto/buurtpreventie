<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\ListOutcomes;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeRepository;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ReadAction;

/**
 * Lists the outcomes by name: the ones a member can choose for a report, or all of them for the admin.
 */
#[ReadAction]
final readonly class ListOutcomesHandler
{
    public function __construct(
        private OutcomeRepository $outcomes,
        private Authorization $authorization,
    ) {
    }

    public function __invoke(ListOutcomes $input): ListOutcomesOutput
    {
        $this->authorization->denyUnlessGranted($input->withArchived ? 'catalogue.manage' : 'catalogue.list');

        $outcomes = \array_filter(
            $this->outcomes->all(),
            static fn(Outcome $outcome): bool => $input->withArchived || !$outcome->isArchived(),
        );

        return new ListOutcomesOutput(\array_values(\array_map(
            static fn(Outcome $outcome): array => [
                'id' => $outcome->id()->toRfc4122(),
                'name' => $outcome->name(),
                'kind' => $outcome->kind()->value,
                'archived' => $outcome->isArchived(),
            ],
            $outcomes,
        )));
    }
}
