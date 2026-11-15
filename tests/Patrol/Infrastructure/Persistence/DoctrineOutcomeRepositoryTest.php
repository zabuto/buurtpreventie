<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Persistence;

use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Catalogue\OutcomeNameTaken;
use App\Patrol\Infrastructure\Persistence\DoctrineOutcomeRepository;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\NotFound;
use App\Tests\Database\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Every name gets a unique suffix, so rows of other tests in the same database never collide or match.
 */
#[Group('database')]
final class DoctrineOutcomeRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineOutcomeRepository $repository;
    private string $suffix;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineOutcomeRepository($entityManager);
        $this->suffix = Uuid::v7()->toBase58();
    }

    #[Test]
    public function it_brings_a_outcome_back_by_id(): void
    {
        $outcome = $this->stored('Café');
        $this->entityManager->clear();

        $read = $this->repository->get($outcome->id());

        $this->assertSame('Café ' . $this->suffix, $read->name());
    }

    #[Test]
    public function it_reports_an_unknown_outcome(): void
    {
        $this->expectException(NotFound::class);

        $this->repository->get(Uuid::v7());
    }

    #[Test]
    public function it_finds_an_archived_outcome_by_its_name_key(): void
    {
        $outcome = $this->outcome('Café');
        $outcome->archive();
        $this->repository->add($outcome);
        $this->entityManager->clear();

        $found = $this->repository->findByNameKey(LookupKey::fromName('CAFE ' . $this->suffix));

        $this->assertSame($outcome->id()->toRfc4122(), $found?->id()->toRfc4122());
    }

    #[Test]
    public function it_finds_no_outcome_by_a_name_key_nobody_has(): void
    {
        $this->assertNull($this->repository->findByNameKey(LookupKey::fromName('Nergens ' . $this->suffix)));
    }

    #[Test]
    public function it_turns_a_name_an_archived_outcome_has_into_a_clash_that_names_it(): void
    {
        $archived = $this->outcome('Café');
        $archived->archive();
        $this->repository->add($archived);

        try {
            $this->repository->add($this->outcome('cafe'));
            $this->fail('A second outcome with the same name key must clash.');
        } catch (OutcomeNameTaken $clash) {
            $this->assertSame([$archived->id()->toRfc4122(), true], [$clash->existingId->toRfc4122(), $clash->existingIsArchived]);
        }
    }

    #[Test]
    public function it_turns_a_new_name_another_outcome_has_into_a_clash(): void
    {
        $this->stored('Kerkplein');
        $renamed = $this->stored('Speeltuin');
        $renamed->rename('kerkplein ' . $this->suffix);

        $this->expectException(OutcomeNameTaken::class);

        $this->repository->update($renamed);
    }

    private function outcome(string $name): Outcome
    {
        $name .= ' ' . $this->suffix;

        return Outcome::create($name, OutcomeKind::Remarks);
    }

    private function stored(string $name): Outcome
    {
        $outcome = $this->outcome($name);
        $this->repository->add($outcome);

        return $outcome;
    }
}
