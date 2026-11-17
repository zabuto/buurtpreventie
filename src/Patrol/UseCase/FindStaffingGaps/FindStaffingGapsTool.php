<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\FindStaffingGaps;

use App\Shared\Infrastructure\Mcp\ToolInput;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'staffing_gaps',
    description: 'Upcoming rounds that still need walkers to reach their minimum, earliest first, with the number of walkers each needs, its open spots and whether you walk it already (signedUp): a round of your own still seeks a buddy, the others you can join.',
    annotations: new ToolAnnotations(title: 'Rounds that seek a buddy', readOnlyHint: true, openWorldHint: false),
)]
final readonly class FindStaffingGapsTool
{
    public function __construct(
        private FindStaffingGapsHandler $handler,
        private ToolInput $input,
    ) {
    }

    /**
     * @param int|null $days only rounds that start within this many days from now, 1 to 31; leave out for every upcoming round
     */
    public function __invoke(#[Schema(type: 'integer')] ?int $days = null): FindStaffingGapsOutput
    {
        return ($this->handler)($this->input->validated(new FindStaffingGaps($days)));
    }
}
