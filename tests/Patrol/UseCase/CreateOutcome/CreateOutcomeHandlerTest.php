<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\CreateOutcome;

use App\Patrol\Domain\Outcome;
use App\Patrol\Domain\OutcomeKind;
use App\Patrol\Domain\OutcomeNameTaken;
use App\Patrol\UseCase\CreateOutcome\CreateOutcome;
use App\Patrol\UseCase\CreateOutcome\CreateOutcomeHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\LookupKey;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CreateOutcomeHandlerTest extends TestCase
{
    private InMemoryOutcomeRepository $outcomes;
    private RecordingAuditTrail $auditTrail;

    #[Override]
    protected function setUp(): void
    {
        $this->outcomes = new InMemoryOutcomeRepository();
        $this->auditTrail = new RecordingAuditTrail();
    }

    #[Test]
    public function it_creates_the_outcome_with_its_name_and_kind(): void
    {
        $output = $this->handler()(new CreateOutcome(' Inbraak ', 'incident'));

        $outcome = $this->outcomes->get(Uuid::fromString($output->id));
        $this->assertSame(['Inbraak', OutcomeKind::Incident, false], [$outcome->name(), $outcome->kind(), $outcome->isArchived()]);
    }

    #[Test]
    public function it_records_the_name_and_kind_of_the_new_outcome(): void
    {
        $output = $this->handler()(new CreateOutcome('Inbraak', 'incident'));

        $this->assertEquals(
            [
                new AuditedAction('outcome.created', 'outcome', Uuid::fromString($output->id), [
                    'name' => ['old' => null, 'new' => 'Inbraak'],
                    'kind' => ['old' => null, 'new' => 'incident'],
                ]),
            ],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_refuses_a_name_taken_by_an_active_outcome(): void
    {
        $existing = Outcome::create('Café de Hoek', OutcomeKind::Remarks);
        $this->outcomes->add($existing);

        $refusal = null;
        try {
            $this->handler()(new CreateOutcome('cafe de hoek', 'remarks'));
        } catch (OutcomeNameTaken $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(OutcomeNameTaken::class, $refusal);
        $this->assertEquals([$existing->id(), false], [$refusal->existingId, $refusal->existingIsArchived]);
    }

    /**
     * An archived name stays taken, so the admin restores the item instead of adding a look-alike.
     */
    #[Test]
    public function it_refuses_a_name_taken_by_an_archived_outcome(): void
    {
        $existing = Outcome::create('Café de Hoek', OutcomeKind::Remarks);
        $existing->archive();
        $this->outcomes->add($existing);

        $refusal = null;
        try {
            $this->handler()(new CreateOutcome('cafe de hoek', 'remarks'));
        } catch (OutcomeNameTaken $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(OutcomeNameTaken::class, $refusal);
        $this->assertEquals([$existing->id(), true], [$refusal->existingId, $refusal->existingIsArchived]);
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new CreateOutcome('Inbraak', 'incident'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['catalogue.manage', null]], $authorization->asked);
        $this->assertSame([null, []], [$this->outcomes->findByNameKey(LookupKey::fromName('Inbraak')), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): CreateOutcomeHandler
    {
        return new CreateOutcomeHandler(
            $this->outcomes,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
