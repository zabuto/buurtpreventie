<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Persistence;

use App\Patrol\Domain\MeetingPoint;
use App\Patrol\Domain\MeetingPointNameTaken;
use App\Patrol\Infrastructure\Persistence\DoctrineMeetingPointRepository;
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
final class DoctrineMeetingPointRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineMeetingPointRepository $repository;
    private string $suffix;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineMeetingPointRepository($entityManager);
        $this->suffix = Uuid::v7()->toBase58();
    }

    #[Test]
    public function it_brings_a_meetingPoint_back_by_id(): void
    {
        $meetingPoint = $this->stored('Café');
        $this->entityManager->clear();

        $read = $this->repository->get($meetingPoint->id());

        $this->assertSame('Café ' . $this->suffix, $read->name());
    }

    #[Test]
    public function it_reports_an_unknown_meetingPoint(): void
    {
        $this->expectException(NotFound::class);

        $this->repository->get(Uuid::v7());
    }

    #[Test]
    public function it_finds_an_archived_meetingPoint_by_its_name_key(): void
    {
        $meetingPoint = $this->meetingPoint('Café');
        $meetingPoint->archive();
        $this->repository->add($meetingPoint);
        $this->entityManager->clear();

        $found = $this->repository->findByNameKey(LookupKey::fromName('CAFE ' . $this->suffix));

        $this->assertSame($meetingPoint->id()->toRfc4122(), $found?->id()->toRfc4122());
    }

    #[Test]
    public function it_finds_no_meetingPoint_by_a_name_key_nobody_has(): void
    {
        $this->assertNull($this->repository->findByNameKey(LookupKey::fromName('Nergens ' . $this->suffix)));
    }

    #[Test]
    public function it_turns_a_name_an_archived_meetingPoint_has_into_a_clash_that_names_it(): void
    {
        $archived = $this->meetingPoint('Café');
        $archived->archive();
        $this->repository->add($archived);

        try {
            $this->repository->add($this->meetingPoint('cafe'));
            $this->fail('A second meeting point with the same name key must clash.');
        } catch (MeetingPointNameTaken $clash) {
            $this->assertSame([$archived->id()->toRfc4122(), true], [$clash->existingId->toRfc4122(), $clash->existingIsArchived]);
        }
    }

    #[Test]
    public function it_turns_a_new_name_another_meetingPoint_has_into_a_clash(): void
    {
        $this->stored('Kerkplein');
        $renamed = $this->stored('Speeltuin');
        $renamed->rename('kerkplein ' . $this->suffix);

        $this->expectException(MeetingPointNameTaken::class);

        $this->repository->update($renamed);
    }

    private function meetingPoint(string $name): MeetingPoint
    {
        $name .= ' ' . $this->suffix;

        return MeetingPoint::create($name);
    }

    private function stored(string $name): MeetingPoint
    {
        $meetingPoint = $this->meetingPoint($name);
        $this->repository->add($meetingPoint);

        return $meetingPoint;
    }
}
