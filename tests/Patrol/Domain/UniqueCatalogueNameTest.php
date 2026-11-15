<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\MeetingPoint;
use App\Patrol\Domain\MeetingPointNameTaken;
use App\Patrol\Domain\Outcome;
use App\Patrol\Domain\OutcomeKind;
use App\Patrol\Domain\OutcomeNameTaken;
use App\Patrol\Domain\UniqueCatalogueName;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UniqueCatalogueNameTest extends TestCase
{
    private InMemoryMeetingPointRepository $meetingPoints;
    private InMemoryOutcomeRepository $outcomes;
    private UniqueCatalogueName $uniqueName;

    #[Override]
    protected function setUp(): void
    {
        $this->meetingPoints = new InMemoryMeetingPointRepository();
        $this->outcomes = new InMemoryOutcomeRepository();
        $this->uniqueName = new UniqueCatalogueName($this->meetingPoints, $this->outcomes);
    }

    #[Test]
    public function it_refuses_a_meeting_point_name_that_collides_on_its_key_and_names_the_holder(): void
    {
        $cafe = MeetingPoint::create('Café');
        $cafe->archive();
        $this->meetingPoints->add($cafe);

        $refusal = null;
        try {
            $this->uniqueName->ensureMeetingPointNameAvailable('cafe');
        } catch (MeetingPointNameTaken $exception) {
            $refusal = $exception;
        }

        $this->assertNotNull($refusal);
        $this->assertSame([$cafe->id(), true], [$refusal->existingId, $refusal->existingIsArchived]);
    }

    #[Test]
    public function it_lets_a_meeting_point_take_another_spelling_of_its_own_name(): void
    {
        $cafe = MeetingPoint::create('Café');
        $this->meetingPoints->add($cafe);

        $this->uniqueName->ensureMeetingPointNameAvailable('CAFÉ', $cafe->id());

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_refuses_an_outcome_name_that_collides_on_its_key_and_names_the_holder(): void
    {
        $incident = Outcome::create('Incident', OutcomeKind::Incident);
        $this->outcomes->add($incident);

        $refusal = null;
        try {
            $this->uniqueName->ensureOutcomeNameAvailable('incident ');
        } catch (OutcomeNameTaken $exception) {
            $refusal = $exception;
        }

        $this->assertNotNull($refusal);
        $this->assertSame([$incident->id(), false], [$refusal->existingId, $refusal->existingIsArchived]);
    }

    #[Test]
    public function it_lets_an_outcome_take_another_spelling_of_its_own_name(): void
    {
        $incident = Outcome::create('Incident', OutcomeKind::Incident);
        $this->outcomes->add($incident);

        $this->uniqueName->ensureOutcomeNameAvailable('INCIDENT', $incident->id());

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_lets_a_meeting_point_and_an_outcome_share_a_name(): void
    {
        $this->outcomes->add(Outcome::create('Speeltuin', OutcomeKind::Remarks));

        $this->uniqueName->ensureMeetingPointNameAvailable('Speeltuin');

        $this->expectNotToPerformAssertions();
    }
}
