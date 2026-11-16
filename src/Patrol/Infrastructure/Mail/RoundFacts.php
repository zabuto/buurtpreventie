<?php

declare(strict_types=1);

namespace App\Patrol\Infrastructure\Mail;

use App\Patrol\Domain\Round\Round;
use App\Shared\Domain\AppUrl;
use App\Shared\Infrastructure\Mail\LocalDateText;
use Symfony\Component\Uid\Uuid;

/**
 * What a mail tells about a round: when, which daypart, where it starts and how many walk, never who. Who walks is
 * in the app, behind the link.
 */
final readonly class RoundFacts
{
    public function __construct(
        private AppUrl $appUrl,
    ) {
    }

    /**
     * @return array{date: string, time: string, daypart: string, meetingPoint: ?string, walkers: int, others: int, link: string}
     *     others: the walkers besides the recipient
     */
    public function of(Round $round, Uuid $recipientId, string $locale): array
    {
        return [
            'date' => LocalDateText::date($round->startsAt(), $locale),
            'time' => LocalDateText::time($round->startsAt(), $locale),
            'daypart' => $round->daypart()->value,
            'meetingPoint' => $round->meetingPoint()?->name(),
            'walkers' => $round->walkerCount(),
            'others' => $round->walkerCount() - ($round->isSignedUp($recipientId) ? 1 : 0),
            'link' => $this->appUrl->to('app/#/rounds/' . $round->id()->toRfc4122()),
        ];
    }
}
