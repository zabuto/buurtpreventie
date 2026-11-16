<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\CreateRound;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Rounds at 22:00 on the evenings around both clock changes of 2027 in Europe/Amsterdam: summer time starts on
 * 28 March and ends on 31 October. Each evening is written with the offset of the day, the other offset and Z, which
 * all name the same instant.
 */
final readonly class ClockChangeEvenings
{
    /**
     * @return iterable<string, array{string, string}> the start as sent and as the API gives it back
     */
    public static function starts(): iterable
    {
        foreach (self::evenings() as $evening => [$local, $other, $utc]) {
            yield $evening . ', local offset' => [$local, $local];
            yield $evening . ', other offset' => [$other, $local];
            yield $evening . ', Z' => [$utc, $local];
        }
    }

    /**
     * @return array<string, array{string, string, string}> the start in local time, with the other offset and in UTC
     */
    public static function evenings(): array
    {
        return [
            'the evening before summer time' => ['2027-03-27T22:00:00+01:00', '2027-03-27T23:00:00+02:00', '2027-03-27T21:00:00Z'],
            'the first evening of summer time' => ['2027-03-28T22:00:00+02:00', '2027-03-28T21:00:00+01:00', '2027-03-28T20:00:00Z'],
            'the last evening of summer time' => ['2027-10-30T22:00:00+02:00', '2027-10-30T21:00:00+01:00', '2027-10-30T20:00:00Z'],
            'the first evening of winter time' => ['2027-10-31T22:00:00+01:00', '2027-10-31T23:00:00+02:00', '2027-10-31T21:00:00Z'],
        ];
    }

    /**
     * The local day of a start as a period for GET /rounds, in UTC so the query string needs no encoded +.
     *
     * @return array{from: string, to: string}
     */
    public static function dayAround(string $localStart): array
    {
        $start = new DateTimeImmutable($localStart);

        return [
            'from' => $start->modify('-12 hours')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'to' => $start->modify('+12 hours')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
