<?php

declare(strict_types=1);

namespace App\Tests\Settings\Infrastructure\Persistence;

use App\Settings\Domain\Setting;
use App\Settings\Infrastructure\Persistence\DoctrineSettingRepository;
use App\Tests\Database\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[Group('database')]
final class DoctrineSettingRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineSettingRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineSettingRepository($entityManager);
    }

    #[Test]
    public function it_finds_a_setting_by_name_with_its_changed_value(): void
    {
        $name = 'test.' . Uuid::v7()->toRfc4122();
        $setting = Setting::of($name, '2');
        $this->repository->add($setting);
        $this->entityManager->flush();
        $setting->change('3');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->repository->find($name);

        $this->assertSame('3', $read?->value());
    }

    #[Test]
    public function it_finds_no_setting_by_an_unknown_name(): void
    {
        $this->assertNull($this->repository->find('test.' . Uuid::v7()->toRfc4122()));
    }
}
