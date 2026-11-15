<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\Round;
use App\Patrol\Domain\StaffingRule;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * A round on 20 November 2026 at 22:00 in Europe/Amsterdam, with a minimum of two walkers.
 */
final readonly class RoundFixture
{
    public const string STARTS_AT = '2026-11-20T21:00:00+00:00';
    public const string BEFORE_START = '2026-11-20T20:59:00+00:00';
    public const string AT_START = '2026-11-20T21:00:00+00:00';
    public const string CREATED_AT = '2026-11-10T09:00:00+00:00';

    /**
     * @param int $walkers the creator included; the others sign up when the round is created
     */
    public static function withWalkers(Uuid $creator, int $walkers, int $maximum = 7): Round
    {
        $round = Round::plan(
            startsAt: new DateTimeImmutable(self::STARTS_AT),
            createdBy: $creator,
            staffingRule: new StaffingRule(minimumWalkers: 2, maximumWalkers: $maximum),
            now: new DateTimeImmutable(self::CREATED_AT),
        );
        for ($walker = 1; $walker < $walkers; ++$walker) {
            $round->signUp(Uuid::v7(), new DateTimeImmutable(self::CREATED_AT));
        }

        return $round;
    }
}
