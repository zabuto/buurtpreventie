<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain\Round;

use App\Patrol\Domain\Round\ReportReadingPolicy;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ReportReadingPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, list<Role>|null, bool}>
     */
    public static function noteReaders(): iterable
    {
        yield 'a walker of the round' => [true, [Role::Walker], true];
        yield 'a walker of another round' => [false, [Role::Walker], false];
        yield 'an admin' => [false, [Role::Admin], false];
        yield 'an analyst' => [false, [Role::Analyst], true];
        yield 'a coordinator' => [false, [Role::Coordinator], true];
        yield 'the system' => [false, null, true];
    }

    /**
     * A note may be about a suspect, so it goes no further than the people who need it.
     *
     * @param list<Role>|null $roles null for the system
     */
    #[DataProvider('noteReaders')]
    #[Test]
    public function it_shows_notes_and_comments_to_the_walkers_of_the_round_analysts_and_coordinators(bool $walks, ?array $roles, bool $expected): void
    {
        $actor = null === $roles ? null : new Actor(Uuid::v7(), $roles);
        $round = RoundFixture::withWalkers($walks && null !== $actor ? $actor->memberId : Uuid::v7(), 2);

        $this->assertSame($expected, new ReportReadingPolicy()->maySeeNotes($actor, $round));
    }

    /**
     * @return iterable<string, array{list<Role>, bool}>
     */
    public static function reportReaders(): iterable
    {
        yield 'a walker' => [[Role::Walker], false];
        yield 'an admin' => [[Role::Admin], false];
        yield 'an analyst' => [[Role::Analyst], true];
        yield 'a coordinator' => [[Role::Coordinator], true];
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('reportReaders')]
    #[Test]
    public function it_lets_only_analysts_and_coordinators_list_the_reports(array $roles, bool $expected): void
    {
        $this->assertSame($expected, new ReportReadingPolicy()->mayListReports(new Actor(Uuid::v7(), $roles)));
    }
}
