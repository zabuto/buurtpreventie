<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Persistence\Concurrency;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundStart;
use App\Patrol\Domain\StaffingRule;
use App\Patrol\UseCase\SignUpForRound\SignUpForRound;
use App\Patrol\UseCase\WithdrawFromRound\WithdrawFromRound;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Channel;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\Role;
use App\Tests\Database\TestDatabase;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Review focus 1: two sign-ups at the same moment. One PHP process cannot wait on two connections, so this test
 * runs the first action through the real handler inside a transaction it keeps open, starts the second action in
 * a process of its own, waits until that process waits for the lock on the round, and only then commits.
 *
 * In that order the second action reads the round after the first one committed only when the handler locks the
 * round and counts the walkers with a locking read; without either, it signs up next to the first and the round
 * holds one walker too many. The rows are committed for real, so the test removes them itself.
 */
#[Group('database')]
#[SkipDatabaseRollback]
final class ConcurrentParticipationTest extends KernelTestCase
{
    private const float LOCK_WAIT_TIMEOUT_SECONDS = 10.0;

    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private ParticipationHandlers $handlers;

    /** @var list<Uuid> */
    private array $memberIds = [];

    /** @var list<Uuid> */
    private array $roundIds = [];

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->connection = $entityManager->getConnection();
        $this->handlers = ParticipationKernel::handlers();
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $rounds = \array_map(static fn(Uuid $id): string => $id->toBinary(), $this->roundIds);
        $members = \array_map(static fn(Uuid $id): string => $id->toBinary(), $this->memberIds);
        $this->connection->executeStatement('DELETE FROM audit_entry WHERE subject_id IN (?)', [$rounds], [ArrayParameterType::BINARY]);
        $this->connection->executeStatement('DELETE FROM round WHERE id IN (?)', [$rounds], [ArrayParameterType::BINARY]);
        $this->connection->executeStatement('DELETE FROM member WHERE id IN (?)', [$members], [ArrayParameterType::BINARY]);

        parent::tearDown();
    }

    #[Test]
    public function two_walkers_on_the_last_spot_leave_one_signed_up_and_the_other_refused(): void
    {
        $owner = $this->walker();
        $first = $this->walker();
        $second = $this->walker();
        $round = $this->round($owner, maximumWalkers: 2);

        $this->connection->beginTransaction();
        $this->signUp($round, $first);
        $outcome = $this->whileHoldingTheLock($round, $second);

        $this->assertSame(['RoundIsFull', 2], [$outcome, $this->walkerCount($round)]);
    }

    #[Test]
    public function the_same_walker_signing_up_twice_at_once_is_signed_up_once_without_error(): void
    {
        $owner = $this->walker();
        $walker = $this->walker();
        $round = $this->round($owner, maximumWalkers: 7);

        $this->connection->beginTransaction();
        $this->signUp($round, $walker);
        $outcome = $this->whileHoldingTheLock($round, $walker);

        $this->assertSame(['signed up', 2], [$outcome, $this->walkerCount($round)]);
    }

    #[Test]
    public function a_sign_up_while_the_last_walker_leaves_finds_the_round_gone(): void
    {
        $owner = $this->walker();
        $walker = $this->walker();
        $round = $this->round($owner, maximumWalkers: 7);

        $this->connection->beginTransaction();
        $this->handlers->runAs->member(
            new Actor($owner, [Role::Walker]),
            Channel::Console,
            fn() => ($this->handlers->withdraw)(new WithdrawFromRound($round->toRfc4122())),
        );
        $outcome = $this->whileHoldingTheLock($round, $walker);

        $this->assertSame(['NotFound', 0], [$outcome, $this->numberOf('SELECT COUNT(*) FROM round WHERE id = ?', $round)]);
    }

    private function signUp(Uuid $round, Uuid $walker): void
    {
        $this->handlers->runAs->member(
            new Actor($walker, [Role::Walker]),
            Channel::Console,
            fn() => ($this->handlers->signUp)(new SignUpForRound($round->toRfc4122())),
        );
    }

    /**
     * Starts the racing sign-up, commits the open transaction once that sign-up waits for the lock on the round, and
     * returns what the racing process reports.
     */
    private function whileHoldingTheLock(Uuid $round, Uuid $walker): string
    {
        $process = \proc_open(
            [\PHP_BINARY, __DIR__ . '/sign-up.php', $round->toRfc4122(), $walker->toRfc4122()],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [...\getenv(), 'APP_ENV' => 'test'],
        );
        $this->assertIsResource($process);

        $waited = $this->aTransactionWaitsForALock();
        $this->connection->commit();

        [1 => $stdout, 2 => $stderr] = $pipes;
        $this->assertIsResource($stdout);
        $this->assertIsResource($stderr);
        $output = \trim((string) \stream_get_contents($stdout));
        $errors = \trim((string) \stream_get_contents($stderr));
        \proc_close($process);
        $this->assertSame(
            [true, ''],
            [$waited, $errors],
            \sprintf('The racing sign-up, which reported "%s", never waited for the lock on the round.', $output),
        );

        return $output;
    }

    /**
     * The process list shows the sessions of the own user without the PROCESS privilege that the InnoDB lock tables
     * need. A locking read that stays in it waits for the lock; without a lock it ends long before the next look.
     */
    private function aTransactionWaitsForALock(): bool
    {
        $deadline = \microtime(true) + self::LOCK_WAIT_TIMEOUT_SECONDS;
        while (\microtime(true) < $deadline) {
            $waiting = $this->numberOf(
                "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID <> CONNECTION_ID() AND INFO LIKE '%FOR UPDATE%'",
            );
            if (0 < $waiting) {
                return true;
            }

            \usleep(20_000);
        }

        return false;
    }

    private function walkerCount(Uuid $round): int
    {
        return $this->numberOf('SELECT COUNT(*) FROM participation WHERE round_id = ?', $round);
    }

    private function numberOf(string $query, Uuid ...$parameters): int
    {
        $count = $this->connection->fetchOne($query, \array_values(\array_map(static fn(Uuid $id): string => $id->toBinary(), $parameters)));
        $this->assertIsNumeric($count);

        return (int) $count;
    }

    private function walker(): Uuid
    {
        $suffix = Uuid::v7()->toBase58();
        $member = Member::register(new MemberDetails(EmailAddress::fromString($suffix . '@example.org'), 'Loper ' . $suffix, 'Loper ' . $suffix));
        $member->changeRoles([Role::Walker]);
        $this->entityManager->persist($member);
        $this->entityManager->flush();
        $this->memberIds[] = $member->id();

        return $member->id();
    }

    private function round(Uuid $owner, int $maximumWalkers): Uuid
    {
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable('+1 day')),
            $owner,
            new StaffingRule(minimumWalkers: 2, maximumWalkers: $maximumWalkers),
            new DateTimeImmutable(),
        );
        $this->entityManager->persist($round);
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->roundIds[] = $round->id();

        return $round->id();
    }
}
