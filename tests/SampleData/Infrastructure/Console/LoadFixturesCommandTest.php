<?php

declare(strict_types=1);

namespace App\Tests\SampleData\Infrastructure\Console;

use App\Identity\Domain\PasswordHasher;
use App\SampleData\Infrastructure\Fixtures\SamplePassword;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Loads each set into the test database, inside the transaction the test rolls back, so the purge leaves only the
 * rows of the set. The facts come from the Sample data section of the plan and are checked on what the set stored.
 */
#[Group('database')]
final class LoadFixturesCommandTest extends KernelTestCase
{
    private const string LOCAL_ZONE = 'Europe/Amsterdam';

    private Connection $connection;
    private ClockInterface $realClock;

    #[Override]
    protected function setUp(): void
    {
        // Before the database check, so tearDown() can restore the clock after a skip.
        $this->realClock = Clock::get();
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->connection = $entityManager->getConnection();
    }

    #[Override]
    protected function tearDown(): void
    {
        Clock::set($this->realClock);

        parent::tearDown();
    }

    #[Test]
    public function it_builds_the_same_people_and_pattern_from_today_on_any_day(): void
    {
        $this->load('demo', '2026-11-15T12:00:00+01:00');
        $first = $this->pattern('2026-11-15T12:00:00+01:00');

        $this->load('demo', '2027-04-02T09:30:00+02:00');
        $second = $this->pattern('2027-04-02T09:30:00+02:00');

        $this->assertNotSame([], $first['rounds']);
        $this->assertSame($first, $second);
    }

    #[Test]
    public function it_empties_the_database_before_loading(): void
    {
        $this->load('test', '2026-11-15T12:00:00+01:00');
        $this->load('test', '2026-11-15T12:00:00+01:00');

        $this->assertSame(1, $this->number('SELECT COUNT(*) FROM member'));
    }

    #[Test]
    public function it_keeps_the_test_set_minimal(): void
    {
        $this->load('test', '2026-11-15T12:00:00+01:00');

        $this->assertSame(
            ['members' => 1, 'admins' => 1, 'meeting points' => 1, 'outcomes' => 3, 'rounds' => 0],
            [
                'members' => $this->number('SELECT COUNT(*) FROM member'),
                'admins' => $this->number("SELECT COUNT(*) FROM member WHERE FIND_IN_SET('admin', roles) > 0"),
                'meeting points' => $this->number('SELECT COUNT(*) FROM meeting_point'),
                'outcomes' => $this->number('SELECT COUNT(*) FROM outcome'),
                'rounds' => $this->number('SELECT COUNT(*) FROM round'),
            ],
        );
    }

    #[Test]
    public function it_keeps_the_demo_set_within_the_limits_of_the_plan(): void
    {
        $now = '2026-11-15T12:00:00+01:00';
        $this->load('demo', $now);
        $rounds = $this->rounds($now);
        $active = \array_filter($rounds, static fn(array $round): bool => !$round['cancelled']);
        $days = \array_column($rounds, 'days');
        $walked = \array_filter($active, static fn(array $round): bool => $round['days'] < 0 && $round['walkers'] >= $round['minimum']);

        $this->assertSame(
            [
                'at most 20 members' => true,
                'three members with a role other than walker' => 3,
                'only addresses on example.org' => 0,
                'rounds from three months back' => true,
                'rounds up to six weeks ahead' => true,
                'more than a quarter without a buddy' => true,
                'a few full rounds' => true,
                'mostly evenings' => true,
                'sometimes a morning or afternoon' => true,
                'an incident now and then' => true,
                'a few walked rounds without a report' => true,
                'a deactivated member' => 1,
                'an erased member in the history' => 1,
                'a member who uses the API' => 1,
                'members with and without consent' => true,
                'audit entries' => true,
            ],
            [
                'at most 20 members' => $this->number('SELECT COUNT(*) FROM member') <= 20,
                'three members with a role other than walker' => $this->number(
                    "SELECT COUNT(*) FROM member WHERE FIND_IN_SET('coordinator', roles) + FIND_IN_SET('analyst', roles) + FIND_IN_SET('admin', roles) > 0",
                ),
                'only addresses on example.org' => $this->number("SELECT COUNT(*) FROM member WHERE email NOT LIKE '%@example.org'"),
                'rounds from three months back' => [] !== $days && \min($days) >= -92 && \min($days) <= -85,
                'rounds up to six weeks ahead' => [] !== $days && \max($days) <= 42 && \max($days) >= 35,
                'more than a quarter without a buddy' =>
                    (\count(\array_filter($active, static fn(array $round): bool => $round['walkers'] < $round['minimum'])) * 4) > \count($active),
                'a few full rounds' => \count(\array_filter($active, static fn(array $round): bool => $round['walkers'] === $round['maximum'])) >= 2,
                'mostly evenings' => (\count(\array_filter($rounds, static fn(array $round): bool => $round['time'] >= '18:00')) * 4) > (\count($rounds) * 3),
                'sometimes a morning or afternoon' => [] !== \array_filter($rounds, static fn(array $round): bool => $round['time'] < '18:00'),
                'an incident now and then' => [] !== \array_filter($walked, static fn(array $round): bool => 'incident' === $round['report']),
                'a few walked rounds without a report' => [] !== \array_filter($walked, static fn(array $round): bool => null === $round['report']),
                'a deactivated member' => $this->number('SELECT COUNT(*) FROM member WHERE active = 0 AND erased_at IS NULL'),
                'an erased member in the history' => $this->number(
                    'SELECT COUNT(DISTINCT member.id) FROM member JOIN participation ON participation.member_id = member.id WHERE member.erased_at IS NOT NULL',
                ),
                'a member who uses the API' => $this->number('SELECT COUNT(DISTINCT member_id) FROM api_token'),
                'members with and without consent' => $this->number('SELECT COUNT(DISTINCT consent_contact_share) FROM member WHERE erased_at IS NULL') === 2,
                'audit entries' => $this->number('SELECT COUNT(*) FROM audit_entry') > 0,
            ],
        );
    }

    #[Test]
    public function it_holds_every_hard_case_of_the_plan_in_the_edge_set(): void
    {
        $now = '2026-11-15T12:00:00+01:00';
        $this->load('edge', $now);
        $rounds = $this->rounds($now);
        $upcoming = \array_filter($rounds, static fn(array $round): bool => $round['days'] >= 0);

        $this->assertSame(
            [
                'a round at 22:00 on the day before and the day of a clock change' => true,
                'the last walker who withdrew' => true,
                'an erased member in the history' => true,
                'a member without consent' => true,
                'a round just under the minimum' => true,
                'a round at the maximum' => true,
                'a round with one spot left' => true,
                'two members with the same name and a display name each' => true,
                'a name whose lookup key keeps a letter that does not decompose' => true,
            ],
            [
                'a round at 22:00 on the day before and the day of a clock change' => $this->hasRoundsAroundAClockChange(),
                'the last walker who withdrew' =>
                    $this->number(
                        "SELECT COUNT(*) FROM audit_entry deleted WHERE deleted.action = 'round.deleted' AND EXISTS (
                        SELECT 1 FROM audit_entry withdrawn WHERE withdrawn.action = 'participation.withdrawn' AND withdrawn.subject_id = deleted.subject_id
                    )",
                    ) > 0,
                'an erased member in the history' =>
                    $this->number(
                        'SELECT COUNT(*) FROM member JOIN participation ON participation.member_id = member.id WHERE member.erased_at IS NOT NULL',
                    ) > 0,
                'a member without consent' =>
                    $this->number('SELECT COUNT(*) FROM member WHERE consent_contact_share = 0 AND active = 1 AND erased_at IS NULL') > 0,
                'a round just under the minimum' => [] !== \array_filter(
                    $upcoming,
                    static fn(array $round): bool => $round['walkers'] === ($round['minimum'] - 1),
                ),
                'a round at the maximum' => [] !== \array_filter($upcoming, static fn(array $round): bool => $round['walkers'] === $round['maximum']),
                'a round with one spot left' => [] !== \array_filter($upcoming, static fn(array $round): bool => $round['walkers'] === ($round['maximum'] - 1)),
                'two members with the same name and a display name each' =>
                    $this->number(
                        'SELECT COUNT(*) FROM (SELECT name FROM member WHERE erased_at IS NULL GROUP BY name HAVING COUNT(DISTINCT display_name) > 1) shared',
                    ) > 0,
                'a name whose lookup key keeps a letter that does not decompose' => $this->number("SELECT COUNT(*) FROM member WHERE name_key LIKE '%ł%'") > 0,
            ],
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sets(): iterable
    {
        yield 'demo' => ['demo'];
        yield 'test' => ['test'];
        yield 'edge' => ['edge'];
    }

    /**
     * Every set starts with the admin at the same address, so one login and one set of Hurl variables fit any set.
     */
    #[DataProvider('sets')]
    #[Test]
    public function it_lets_the_first_admin_of_every_set_log_in_with_the_sample_password(string $set): void
    {
        $this->load($set, '2026-11-15T12:00:00+01:00');

        $hash = $this->connection->fetchOne(
            "SELECT a.password_hash FROM account a JOIN member m ON m.id = a.member_id WHERE m.email = 'beheer@example.org'",
        );

        $this->assertIsString($hash);
        $this->assertTrue($this->passwordHasher()->verify($hash, SamplePassword::VALUE));
    }

    /**
     * One member per role, and walkers in three situations; every hash costs the real hasher half a second.
     */
    #[Test]
    public function it_gives_the_sample_password_to_one_member_per_role_and_three_walkers_in_the_demo_set(): void
    {
        $this->load('demo', '2026-11-15T12:00:00+01:00');

        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.email, a.password_hash FROM member m JOIN account a ON a.member_id = m.id WHERE a.password_hash IS NOT NULL ORDER BY m.email',
        );

        $this->assertSame(
            [
                'beheer@example.org',
                'fatima.elamrani@example.org',
                'jan.bakker@example.org',
                'priya.sharma@example.org',
                'thomas.visser@example.org',
                'willem.dekker@example.org',
            ],
            \array_column($rows, 'email'),
        );
        foreach ($rows as $row) {
            $this->assertTrue(\is_string($row['password_hash']) && $this->passwordHasher()->verify($row['password_hash'], SamplePassword::VALUE));
        }
    }

    #[Test]
    public function it_shows_the_sample_password_after_loading(): void
    {
        Clock::set(new MockClock(new DateTimeImmutable('2026-11-15T12:00:00+01:00')));
        $tester = $this->tester();

        $tester->execute(['set' => 'test'], ['interactive' => false]);

        $this->assertStringContainsString(SamplePassword::VALUE, $tester->getDisplay());
    }

    /**
     * The curl scenario of docs/getting-started.md signs the demo member up with this token, so it may write.
     */
    #[Test]
    public function it_shows_the_api_token_of_the_demo_member_that_reads_and_writes(): void
    {
        Clock::set(new MockClock(new DateTimeImmutable('2026-11-15T12:00:00+01:00')));
        $tester = $this->tester();

        $tester->execute(['set' => 'demo'], ['interactive' => false]);

        $this->assertSame(1, \preg_match('/bpt_[0-9a-f]{64}/', $tester->getDisplay(), $secret));
        $token = $this->connection->fetchAssociative(
            'SELECT m.email, t.scopes FROM api_token t JOIN member m ON m.id = t.member_id WHERE t.secret_hash = UNHEX(?) AND t.expires_at > ?',
            [\hash('sha256', $secret[0]), '2026-11-15 11:00:00'],
        );
        $this->assertSame(['email' => 'priya.sharma@example.org', 'scopes' => 'read,write'], $token);
    }

    #[Test]
    public function it_refuses_an_unknown_set_and_keeps_the_database(): void
    {
        $this->load('test', '2026-11-15T12:00:00+01:00');

        $tester = $this->tester();
        $exitCode = $tester->execute(['set' => 'v3'], ['interactive' => false]);

        $this->assertSame([Command::INVALID, 1], [$exitCode, $this->number('SELECT COUNT(*) FROM member')]);
    }

    private function load(string $set, string $now): void
    {
        Clock::set(new MockClock(new DateTimeImmutable($now)));

        $tester = $this->tester();
        $tester->execute(['set' => $set], ['interactive' => false]);

        $tester->assertCommandIsSuccessful($tester->getDisplay());
    }

    private function passwordHasher(): PasswordHasher
    {
        $hasher = self::getContainer()->get(PasswordHasher::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(PasswordHasher::class, $hasher);

        return $hasher;
    }

    private function tester(): CommandTester
    {
        $kernel = self::$kernel;
        \assert(null !== $kernel, 'setUp boots the kernel; booting another would leave the rolled-back connection.');

        return new CommandTester(new Application($kernel)->find('app:fixtures:load'));
    }

    /**
     * Who and what, without ids or dates: the same seed builds the same of both.
     *
     * @return array{members: list<array<string, mixed>>, rounds: list<array<string, mixed>>, audit: list<array<string, mixed>>}
     */
    private function pattern(string $now): array
    {
        return [
            'members' => $this->connection->fetchAllAssociative(
                'SELECT name, display_name, email, phone, roles, active, consent_contact_share, notify_open_rounds FROM member ORDER BY display_name',
            ),
            'rounds' => $this->rounds($now),
            'audit' => $this->connection->fetchAllAssociative(
                'SELECT action, COUNT(*) AS entries FROM audit_entry GROUP BY action ORDER BY action',
            ),
        ];
    }

    /**
     * Each round by its local day relative to today and its local time, so two loads on different days compare.
     *
     * @return list<array{days: int, time: string, minimum: int, maximum: int, walkers: int, cancelled: bool, report: string|null}>
     */
    private function rounds(string $now): array
    {
        $zone = new DateTimeZone(self::LOCAL_ZONE);
        $today = new DateTimeImmutable($now)
            ->setTimezone($zone)
            ->setTime(0, 0)
        ;

        $rows = $this->connection->fetchAllAssociative(
            'SELECT round.starts_at, round.minimum_walkers, round.maximum_walkers, round.cancelled_at,
                (SELECT COUNT(*) FROM participation WHERE participation.round_id = round.id) AS walkers,
                (SELECT outcome.kind FROM round_report JOIN outcome ON outcome.id = round_report.outcome_id WHERE round_report.round_id = round.id) AS report
            FROM round ORDER BY round.starts_at',
        );

        return \array_map(static function (array $row) use ($zone, $today): array {
            $start = self::utc($row['starts_at'])->setTimezone($zone);

            return [
                'days' => (int) $today->diff($start->setTime(0, 0))->format('%r%a'),
                'time' => $start->format('H:i'),
                'minimum' => self::integer($row['minimum_walkers']),
                'maximum' => self::integer($row['maximum_walkers']),
                'walkers' => self::integer($row['walkers']),
                'cancelled' => null !== $row['cancelled_at'],
                'report' => null === $row['report'] ? null : self::text($row['report']),
            ];
        }, $rows);
    }

    private function hasRoundsAroundAClockChange(): bool
    {
        $starts = $this->connection->fetchFirstColumn('SELECT starts_at FROM round ORDER BY starts_at');
        $zone = new DateTimeZone(self::LOCAL_ZONE);
        $local = \array_map(static fn(mixed $start): DateTimeImmutable => self::utc($start)->setTimezone($zone), $starts);

        foreach ($local as $before) {
            foreach ($local as $after) {
                if (
                    '22:00' === $before->format('H:i')
                    && '22:00' === $after->format('H:i')
                    && $before->modify('+1 day')->format('Y-m-d') === $after->format('Y-m-d')
                    && $before->getOffset() !== $after->getOffset()
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function number(string $sql): int
    {
        return self::integer($this->connection->fetchOne($sql));
    }

    private static function utc(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable(self::text($value), new DateTimeZone('UTC'));
    }

    private static function text(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }

    private static function integer(mixed $value): int
    {
        self::assertIsNumeric($value);

        return (int) $value;
    }
}
