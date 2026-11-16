<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Persistence;

use App\Settings\Domain\Setting;
use App\Settings\Domain\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineSettingRepository implements SettingRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $name): ?Setting
    {
        return $this->entityManager->find(Setting::class, $name);
    }

    #[Override]
    public function all(): array
    {
        return $this->entityManager->getRepository(Setting::class)->findAll();
    }

    #[Override]
    public function add(Setting $setting): void
    {
        $this->entityManager->persist($setting);
    }

    #[Override]
    public function remove(Setting $setting): void
    {
        $this->entityManager->remove($setting);
    }
}
