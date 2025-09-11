<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AbstractBaseEntity;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use LogicException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractDataFixtures extends Fixture implements DependentFixtureInterface
{
    protected ObjectManager $manager;

    public function __construct(
        protected readonly TranslatorInterface $translator,
        private readonly ValidatorInterface    $validator,
    )
    {
    }

    protected function addFixture(object $entity, ?string $reference = null): void
    {
        if (($entity instanceof AbstractBaseEntity)) {
            if (null === $entity->getCreatedAt()) {
                $created = sprintf('%s 11:11:11', date('Y-m-d'));
                $entity->setCreatedAt(DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $created));
            }

            if (null === $entity->getCreatedBy()) {
                $user = $this->getReference(UserFixtures::ADMIN_REFERENCE, User::class);
                $entity->setCreatedBy($user);
            }
        }

        $errors = $this->validator->validate($entity);
        if (count($errors) > 0) {
            throw new LogicException($this->translator->trans('exception.datafixture.invalid'));
        }

        $this->manager->persist($entity);
        $this->manager->flush();

        if (null !== $reference) {
            $this->addReference($reference, $entity);
        }
    }

    protected function setManager(ObjectManager $manager): void
    {
        $this->manager = $manager;
    }
}
