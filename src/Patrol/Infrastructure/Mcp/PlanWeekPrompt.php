<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Mcp;

use App\Settings\UseCase\GetPublicConfig\GetPublicConfig;
use App\Settings\UseCase\GetPublicConfig\GetPublicConfigHandler;
use App\Shared\Domain\LocalTime;
use DateTimeImmutable;
use DateTimeZone;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Exception\PromptGetException;
use Psr\Clock\ClockInterface;

/**
 * Asks the client which rounds of a week still need walkers and where no round is planned. The prompt holds no data,
 * and it asks the client to change nothing without the member's say.
 */
#[McpPrompt(
    name: 'plan_week',
    title: 'Plan a week',
    description: 'The open spots of a week: rounds that still need walkers, and dayparts without a round.',
)]
final readonly class PlanWeekPrompt
{
    public function __construct(
        private GetPublicConfigHandler $config,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param string|null $weekStart the first day of the week, as YYYY-MM-DD; the Monday after today when left out
     *
     * @throws PromptGetException for a date in another format
     *
     * @return list<array{role: string, content: string}>
     */
    public function __invoke(?string $weekStart = null): array
    {
        $start = null === $weekStart
            ? LocalTime::of($this->clock->now())->modify('next monday midnight')
            : DateTimeImmutable::createFromFormat('!Y-m-d', $weekStart, new DateTimeZone(LocalTime::ZONE));
        if (false === $start || null !== $weekStart && $start->format('Y-m-d') !== $weekStart) {
            throw new PromptGetException(\sprintf('The date "%s" is not in the format YYYY-MM-DD.', $weekStart));
        }

        $config = ($this->config)(new GetPublicConfig());
        $from = $start->format(\DATE_ATOM);
        $to = $start->modify('+7 days')->format(\DATE_ATOM);

        return [[
            'role' => 'user',
            'content' => \implode("\n\n", [
                \sprintf('Help plan the neighbourhood watch rounds of %s for the week from %s to %s.', $config->associationName, $from, $to),
                \sprintf('Call list_rounds with from %s and to %s; a round with the status seeking_buddy still needs walkers.', $from, $to),
                'List per day and daypart (morning, afternoon, evening) which rounds still need walkers and how many, and which evenings have no round at all.',
                'Suggest where a walker could sign up or plan a round, but sign nobody up and plan no round without asking first.',
                \sprintf('Write in %s.', LanguageName::of($config->language)),
            ]),
        ]];
    }
}
