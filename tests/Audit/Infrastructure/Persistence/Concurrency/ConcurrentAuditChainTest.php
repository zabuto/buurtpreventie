<?php

declare(strict_types=1);

namespace App\Tests\Audit\Infrastructure\Persistence\Concurrency;

use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChain;
use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChainHandler;
use App\Audit\UseCase\VerifyAuditChain\VerifyAuditChainOutput;
use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Patrol\UseCase\SignUpForRound\SignUpForRound;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Channel;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\Role;
use App\Tests\Database\TestDatabase;
use App\Tests\Patrol\Infrastructure\Persistence\Concurrency\ParticipationHandlers;
use App\Tests\Patrol\Infrastructure\Persistence\Concurrency\ParticipationKernel;
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
 * Two transactions that write an audit entry at the same moment. This test signs a walker up through the real handler
 * inside a transaction it keeps open, which holds the head of the chain once the handler is done, starts a second
 * sign-up in a process of its own, waits until that process waits for a lock, and only then commits.
 *
 * Without the lock on the head both transactions would link to the same entry: two entries with one position, or one
 * position refused by the unique index. With the lock taken any earlier than the last step, a transaction holding the
 * head while it waits for a round could deadlock with one holding the round.
 *
 * The rows are committed for real, so the test empties the log before and after itself and sets head and anchor to
 * the start, as the migration leaves an empty log.
 */
#[Group('database')]
#[SkipDatabaseRollback]
final class ConcurrentAuditChainTest extends KernelTestCase
{
    private const float LOCK_WAIT_TIMEOUT_SECONDS = 10.0;

    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private ParticipationHandlers $handlers;

    /** Without a database setUp skips before it connects, and tearDown has nothing to remove. */
    private bool $connected = false;

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
        $this->connected = true;
        $this->handlers = ParticipationKernel::handlers();
        $this->emptyTheLog();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!$this->connected) {
            parent::tearDown();

            return;
        }
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $this->emptyTheLog();
        $rounds = \array_map(static fn(Uuid $id): string => $id->toBinary(), $this->roundIds);
        $members = \array_map(static fn(Uuid $id): string => $id->toBinary(), $this->memberIds);
        $this->connection->executeStatement('DELETE FROM round WHERE id IN (?)', [$rounds], [ArrayParameterType::BINARY]);
        $this->connection->executeStatement('DELETE FROM member WHERE id IN (?)', [$members], [ArrayParameterType::BINARY]);

        parent::tearDown();
    }

    #[Test]
    public function two_writers_at_once_take_consecutive_positions_in_an_intact_chain(): void
    {
        $owner = $this->walker();
        $first = $this->walker();
        $second = $this->walker();
        $oneRound = $this->round($owner);
        $otherRound = $this->round($owner);

        $this->connection->beginTransaction();
        $this->signUp($oneRound, $first);
        $outcome = $this->whileHoldingTheHead($otherRound, $second);

        $verified = $this->verify();
        $this->assertSame(
            ['signed up', [1, 2], true, 2],
            [$outcome, $this->positions(), $verified->intact, $verified->intactThrough],
        );
    }

    /**
     * The second sign-up waits for the round, not the head: the head is taken only as the last step, so the first
     * transaction never holds it while it waits for anything else.
     */
    #[Test]
    public function a_writer_waiting_for_a_round_does_not_deadlock_with_the_one_holding_round_and_head(): void
    {
        $owner = $this->walker();
        $first = $this->walker();
        $second = $this->walker();
        $round = $this->round($owner);

        $this->connection->beginTransaction();
        $this->signUp($round, $first);
        $outcome = $this->whileHoldingTheHead($round, $second);

        $verified = $this->verify();
        $this->assertSame(['signed up', [1, 2], true], [$outcome, $this->positions(), $verified->intact]);
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
     * Starts the racing sign-up, commits the open transaction once that sign-up waits for a lock, and returns what the
     * racing process reports.
     */
    private function whileHoldingTheHead(Uuid $round, Uuid $walker): string
    {
        $process = \proc_open(
            [\PHP_BINARY, __DIR__ . '/../../../../Patrol/Infrastructure/Persistence/Concurrency/sign-up.php', $round->toRfc4122(), $walker->toRfc4122()],
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
            \sprintf('The racing sign-up, which reported "%s", never waited for a lock.', $output),
        );

        return $output;
    }

    /**
     * A locking read of another session that stays in the process list waits for a lock; see
     * ConcurrentParticipationTest for why the process list rather than the InnoDB lock tables.
     */
    private function aTransactionWaitsForALock(): bool
    {
        $deadline = \microtime(true) + self::LOCK_WAIT_TIMEOUT_SECONDS;
        while (\microtime(true) < $deadline) {
            $waiting = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID <> CONNECTION_ID() AND INFO LIKE '%FOR UPDATE%'",
            );
            $this->assertIsNumeric($waiting);
            if (0 < (int) $waiting) {
                return true;
            }

            \usleep(20_000);
        }

        return false;
    }

    private function verify(): VerifyAuditChainOutput
    {
        $handler = self::getContainer()->get(VerifyAuditChainHandler::class);
        // @phpstan-ignore function.alreadyNarrowedType, instanceof.alwaysTrue (PHPStan reads the type from the container, Mago does not)
        \assert($handler instanceof VerifyAuditChainHandler, 'The console keeps the handler in the container.');
        $this->entityManager->clear();

        return $this->handlers->runAs->system(Channel::Console, static fn(): VerifyAuditChainOutput => $handler(new VerifyAuditChain()));
    }

    /**
     * @return list<int>
     */
    private function positions(): array
    {
        return \array_map(
            static fn(mixed $position): int => \is_numeric($position) ? (int) $position : -1,
            $this->connection->fetchFirstColumn('SELECT chain_position FROM audit_entry ORDER BY chain_position'),
        );
    }

    private function emptyTheLog(): void
    {
        $this->connection->executeStatement('DELETE FROM audit_entry');
        $this->connection->executeStatement('DELETE FROM audit_chain');
        $this->connection->executeStatement(
            "INSERT INTO audit_chain (name, chain_position, chain_hash) VALUES ('head', 0, UNHEX(REPEAT('00', 32))), ('anchor', 0, UNHEX(REPEAT('00', 32)))",
        );
    }

    private function walker(): Uuid
    {
        $suffix = Uuid::v7()->toBase58();
        $member = Member::register(new MemberDetails(EmailAddress::fromString($suffix . '@example.org'), 'Walker ' . $suffix, 'Walker ' . $suffix));
        $member->changeRoles([Role::Walker]);
        $this->entityManager->persist($member);
        $this->entityManager->flush();
        $this->memberIds[] = $member->id();

        return $member->id();
    }

    private function round(Uuid $owner): Uuid
    {
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable('+1 day')),
            $owner,
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            new DateTimeImmutable(),
        );
        $this->entityManager->persist($round);
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->roundIds[] = $round->id();

        return $round->id();
    }
}
