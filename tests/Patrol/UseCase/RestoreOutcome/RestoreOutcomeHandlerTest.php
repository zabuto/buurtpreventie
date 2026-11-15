<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\RestoreOutcome;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\UseCase\RestoreOutcome\RestoreOutcome;
use App\Patrol\UseCase\RestoreOutcome\RestoreOutcomeHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\NotFound;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RestoreOutcomeHandlerTest extends TestCase
{
    private InMemoryOutcomeRepository $outcomes;
    private RecordingAuditTrail $auditTrail;
    private Outcome $incident;

    #[Override]
    protected function setUp(): void
    {
        $this->outcomes = new InMemoryOutcomeRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->incident = Outcome::create('Inbraak', OutcomeKind::Incident);
        $this->incident->archive();
        $this->outcomes->add($this->incident);
    }

    #[Test]
    public function it_restores_the_outcome(): void
    {
        $this->handler()(new RestoreOutcome($this->incident->id()->toRfc4122()));

        $this->assertFalse($this->incident->isArchived());
    }

    #[Test]
    public function it_records_the_restoring(): void
    {
        $this->handler()(new RestoreOutcome($this->incident->id()->toRfc4122()));

        $this->assertEquals(
            [new AuditedAction('outcome.restored', 'outcome', $this->incident->id())],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_nothing_for_an_outcome_that_is_active_already(): void
    {
        $this->incident->restore();

        $this->handler()(new RestoreOutcome($this->incident->id()->toRfc4122()));

        $this->assertSame([false, []], [$this->incident->isArchived(), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_refuses_an_unknown_outcome(): void
    {
        $this->expectException(NotFound::class);

        $this->handler()(new RestoreOutcome(Uuid::v7()->toRfc4122()));
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new RestoreOutcome($this->incident->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['catalogue.manage', null]], $authorization->asked);
        $this->assertSame([true, []], [$this->incident->isArchived(), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): RestoreOutcomeHandler
    {
        return new RestoreOutcomeHandler(
            $this->outcomes,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
