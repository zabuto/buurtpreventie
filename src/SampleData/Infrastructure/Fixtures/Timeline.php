<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use DateTimeImmutable;
use LogicException;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Runs each step of a set at its own moment, so a round in the past is created, joined and reported through the use
 * cases as it would have been then, and its audit entries carry that moment.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final class Timeline
{
    private ?DateTimeImmutable $now = null;

    public function begin(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    /**
     * @template T
     *
     * @param callable(): T $step
     *
     * @return T
     *
     * @throws LogicException for a moment after the load began, which would leave a step nobody could have taken yet
     */
    public function at(DateTimeImmutable $moment, callable $step): mixed
    {
        if (null === $this->now || $moment > $this->now) {
            throw new LogicException(\sprintf('A sample step at %s lies after the moment the load began.', $moment->format(\DATE_ATOM)));
        }

        $previous = Clock::get();
        Clock::set(new MockClock($moment));
        try {
            return $step();
        } finally {
            Clock::set($previous);
        }
    }
}
