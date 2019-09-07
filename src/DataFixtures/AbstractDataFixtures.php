<?php

namespace App\DataFixtures;

use App\Entity\AbstractBaseEntity;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Exception;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * AbstractDataFixtures
 */
abstract class AbstractDataFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @var ObjectManager
     */
    protected $manager;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @param  ObjectManager       $manager
     * @param  ValidatorInterface  $validator
     * @param  TranslatorInterface $translator
     */
    public function __construct(ObjectManager $manager, ValidatorInterface $validator, TranslatorInterface $translator)
    {
        $this->manager = $manager;
        $this->validator = $validator;
        $this->translator = $translator;
    }

    /**
     * @return array
     */
    public function getDependencies()
    {
        return [
            UserFixtures::class,
        ];
    }

    /**
     * @param  object $entity
     * @param  string $reference
     * @throws Exception
     */
    protected function addFixture($entity, ?string $reference = null)
    {
        if ($entity instanceof AbstractBaseEntity) {
            if (null === $entity->getCreatedBy()) {
                /** @var UserInterface $user */
                $user = $this->getReference(UserFixtures::ADMIN_REFERENCE);
                $entity->setCreatedBy($user);
            }
        }

        $errors = $this->validator->validate($entity);
        if (count($errors) > 0) {
            throw new Exception('exception.datafixture.invalid');
        }

        $this->manager->persist($entity);
        $this->manager->flush();

        if (null !== $reference) {
            $this->addReference($reference, $entity);
        }
    }
}
