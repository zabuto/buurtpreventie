<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ChangeOutcome;

use App\Patrol\Domain\Outcome;
use App\Patrol\Domain\OutcomeKind;
use App\Patrol\Domain\OutcomeNameTaken;
use App\Patrol\UseCase\ChangeOutcome\ChangeOutcome;
use App\Patrol\UseCase\ChangeOutcome\ChangeOutcomeHandler;
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

final class ChangeOutcomeHandlerTest extends TestCase
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
        $this->outcomes->add($this->incident);
    }

    #[Test]
    public function it_renames_the_outcome_and_keeps_its_kind(): void
    {
        $this->handler()(new ChangeOutcome($this->incident->id()->toRfc4122(), 'Vernieling'));

        $this->assertSame(['Vernieling', OutcomeKind::Incident], [$this->incident->name(), $this->incident->kind()]);
    }

    #[Test]
    public function it_records_the_new_name(): void
    {
        $this->handler()(new ChangeOutcome($this->incident->id()->toRfc4122(), 'Vernieling'));

        $this->assertEquals(
            [
                new AuditedAction('outcome.changed', 'outcome', $this->incident->id(), [
                    'name' => ['old' => 'Inbraak', 'new' => 'Vernieling'],
                ]),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_nothing_when_the_name_is_unchanged(): void
    {
        $this->handler()(new ChangeOutcome($this->incident->id()->toRfc4122(), ' Inbraak '));

        $this->assertSame([], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_allows_another_spelling_of_its_own_name(): void
    {
        $this->handler()(new ChangeOutcome($this->incident->id()->toRfc4122(), 'inbraak'));

        $this->assertSame('inbraak', $this->incident->name());
    }

    #[Test]
    public function it_refuses_a_name_taken_by_an_active_outcome(): void
    {
        $existing = Outcome::create('Café de Hoek', OutcomeKind::Remarks);
        $this->outcomes->add($existing);

        $refusal = null;
        try {
            $this->handler()(new ChangeOutcome($this->incident->id()->toRfc4122(), 'cafe de hoek'));
        } catch (OutcomeNameTaken $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(OutcomeNameTaken::class, $refusal);
        $this->assertEquals([$existing->id(), false], [$refusal->existingId, $refusal->existingIsArchived]);
    }

    #[Test]
    public function it_refuses_a_name_taken_by_an_archived_outcome(): void
    {
        $existing = Outcome::create('Café de Hoek', OutcomeKind::Remarks);
        $existing->archive();
        $this->outcomes->add($existing);

        $refusal = null;
        try {
            $this->handler()(new ChangeOutcome($this->incident->id()->toRfc4122(), 'cafe de hoek'));
        } catch (OutcomeNameTaken $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(OutcomeNameTaken::class, $refusal);
        $this->assertEquals([$existing->id(), true], [$refusal->existingId, $refusal->existingIsArchived]);
    }

    #[Test]
    public function it_refuses_an_unknown_outcome(): void
    {
        $this->expectException(NotFound::class);

        $this->handler()(new ChangeOutcome(Uuid::v7()->toRfc4122(), 'Vernieling'));
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new ChangeOutcome($this->incident->id()->toRfc4122(), 'Vernieling'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['catalogue.manage', null]], $authorization->asked);
        $this->assertSame(['Inbraak', []], [$this->incident->name(), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): ChangeOutcomeHandler
    {
        return new ChangeOutcomeHandler(
            $this->outcomes,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
