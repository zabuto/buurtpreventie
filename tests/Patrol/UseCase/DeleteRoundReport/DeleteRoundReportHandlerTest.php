<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\DeleteRoundReport;

use App\Patrol\Domain\Outcome;
use App\Patrol\Domain\OutcomeKind;
use App\Patrol\Domain\Round;
use App\Patrol\Domain\RoundReport;
use App\Patrol\UseCase\DeleteRoundReport\DeleteRoundReport;
use App\Patrol\UseCase\DeleteRoundReport\DeleteRoundReportHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Tests\Patrol\Domain\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryRoundReportRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DeleteRoundReportHandlerTest extends TestCase
{
    private InMemoryRoundRepository $rounds;
    private RecordingAuditTrail $auditTrail;
    private Round $round;
    private RoundReport $report;

    #[Override]
    protected function setUp(): void
    {
        $this->rounds = new InMemoryRoundRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $jan = Uuid::v7();
        $this->round = RoundFixture::withWalkers($jan, 2);
        $this->rounds->add($this->round);
        $this->report = $this->round->submitReport(
            $jan,
            Outcome::create('Niets te melden', OutcomeKind::NothingToReport),
            null,
            new DateTimeImmutable('2026-11-21T08:00:00+00:00'),
        );
    }

    /**
     * Deleting makes room for a new report, the one way to correct one.
     */
    #[Test]
    public function it_deletes_the_report_of_the_round(): void
    {
        $this->handler()(new DeleteRoundReport($this->report->id()->toRfc4122()));

        $this->assertNull($this->round->report());
    }

    #[Test]
    public function it_records_the_deletion(): void
    {
        $this->handler()(new DeleteRoundReport($this->report->id()->toRfc4122()));

        $this->assertEquals(
            [new AuditedAction('report.deleted', 'round', $this->round->id(), ['report' => ['old' => $this->report->id()->toRfc4122(), 'new' => null]])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new DeleteRoundReport($this->report->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['report.delete', $this->report]], $authorization->asked);
        $this->assertSame([$this->report, []], [$this->round->report(), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): DeleteRoundReportHandler
    {
        return new DeleteRoundReportHandler(
            new InMemoryRoundReportRepository($this->rounds),
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
