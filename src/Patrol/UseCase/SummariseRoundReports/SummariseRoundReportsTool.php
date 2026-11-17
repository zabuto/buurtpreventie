<?php

declare(strict_types=1);

namespace App\Patrol\UseCase\SummariseRoundReports;

use App\Shared\Infrastructure\Mcp\ToolInput;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'report_summary',
    description: 'The reports after the rounds of a period of at most a year, counted per outcome, and the incidents per month. Counts only, no notes or names. For analysts and coordinators.',
    annotations: new ToolAnnotations(title: 'Summary of the reports', readOnlyHint: true, openWorldHint: false),
)]
final readonly class SummariseRoundReportsTool
{
    public function __construct(
        private SummariseRoundReportsHandler $handler,
        private ToolInput $input,
    ) {
    }

    /**
     * @param string $from start of the period, ISO 8601 with offset, such as 2026-10-01T00:00:00+02:00
     * @param string $to end of the period (not included), ISO 8601 with offset, at most 366 days after from
     */
    public function __invoke(string $from, string $to): SummariseRoundReportsOutput
    {
        return ($this->handler)($this->input->validated(new SummariseRoundReports($from, $to)));
    }
}
