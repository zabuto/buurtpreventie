<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ReportRoundResult;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeArchived;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundAlreadyReported;
use App\Patrol\UseCase\ReportRoundResult\ReportRoundResult;
use App\Patrol\UseCase\ReportRoundResult\ReportRoundResultHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Role;
use App\Tests\Patrol\Domain\Round\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * The round of RoundFixture has two walkers and started on 20 November 2026; the clock reads the morning after.
 */
final class ReportRoundResultHandlerTest extends TestCase
{
    private const string NOW = '2026-11-21T08:00:00+00:00';

    private InMemoryRoundRepository $rounds;
    private InMemoryOutcomeRepository $outcomes;
    private RecordingAuditTrail $auditTrail;
    private Uuid $jan;
    private Round $round;
    private Outcome $remarks;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->outcomes = new InMemoryOutcomeRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->jan = Uuid::v7();
        $this->round = RoundFixture::withWalkers($this->jan, 2);
        $this->rounds->add($this->round);
        $this->remarks = Outcome::create('Opmerkingen', OutcomeKind::Remarks);
        $this->outcomes->add($this->remarks);
    }

    #[Test]
    public function it_reports_the_outcome_and_note_by_the_actor(): void
    {
        $output = $this->handler()(new ReportRoundResult($this->round->id()->toRfc4122(), $this->remarks->id()->toRfc4122(), 'Poort stond open'));

        $report = $this->round->report();
        $this->assertNotNull($report);
        $this->assertSame(
            [$report->id()->toRfc4122(), $this->jan->toRfc4122(), $this->remarks, 'Poort stond open'],
            [$output->id, $report->authorId()->toRfc4122(), $report->outcome(), $report->note()],
        );
    }

    #[Test]
    public function it_records_the_report(): void
    {
        $output = $this->handler()(new ReportRoundResult($this->round->id()->toRfc4122(), $this->remarks->id()->toRfc4122()));

        $this->assertEquals(
            [new AuditedAction('report.submitted', 'round', $this->round->id(), [
                'report' => ['old' => null, 'new' => $output->id],
                'outcome' => ['old' => null, 'new' => $this->remarks->id()->toRfc4122()],
            ])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_refuses_an_archived_outcome(): void
    {
        $this->remarks->archive();

        $this->expectExceptionObject(OutcomeArchived::forItem($this->remarks->id()));

        $this->handler()(new ReportRoundResult($this->round->id()->toRfc4122(), $this->remarks->id()->toRfc4122()));
    }

    #[Test]
    public function it_refuses_a_second_report(): void
    {
        $this->handler()(new ReportRoundResult($this->round->id()->toRfc4122(), $this->remarks->id()->toRfc4122()));

        $this->expectExceptionObject(RoundAlreadyReported::forRound($this->round->id()));

        $this->handler()(new ReportRoundResult($this->round->id()->toRfc4122(), $this->remarks->id()->toRfc4122()));
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new ReportRoundResult($this->round->id()->toRfc4122(), $this->remarks->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['report.submit', $this->round]], $authorization->asked);
        $this->assertSame([null, []], [$this->round->report(), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): ReportRoundResultHandler
    {
        return new ReportRoundResultHandler(
            $this->rounds,
            $this->outcomes,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
            new FixedExecutionContext(new Actor($this->jan, [Role::Walker])),
        );
    }
}
