<?php

namespace App\DataFixtures;

use App\Exception\UserInvalidException;
use App\Service\UserService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Exception;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * UserFixtures
 */
class UserFixtures extends Fixture
{
    /** @var string */
    public const SUPER_REFERENCE = 'user-super';

    /** @var string */
    public const ADMIN_REFERENCE = 'user-admin';

    /** @var string */
    public const COORDINATOR_REFERENCE = 'user-coordinator';

    /** @var string */
    public const ANALYST_REFERENCE = 'user-analyst';

    /** @var string */
    public const WALKER1_REFERENCE = 'user-walker-1';

    /** @var string */
    public const WALKER2_REFERENCE = 'user-walker-2';

    /** @var string */
    public const WALKER3_REFERENCE = 'user-walker-3';

    /**
     * @var UserService
     */
    private $service;

    /**
     * @var PhoneNumberUtil
     */
    private $phoneUtil;

    /**
     * @var ObjectManager
     */
    protected $manager;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @param  UserService        $service
     * @param  PhoneNumberUtil    $phoneUtil
     * @param  ObjectManager      $manager
     * @param  ValidatorInterface $validator
     */
    public function __construct(UserService $service, PhoneNumberUtil $phoneUtil, ObjectManager $manager, ValidatorInterface $validator)
    {
        $this->service = $service;
        $this->phoneUtil = $phoneUtil::getInstance();
        $this->manager = $manager;
        $this->validator = $validator;
    }

    /**
     * @param  ObjectManager $manager
     * @throws UserInvalidException
     * @throws NumberParseException
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {
        $super = $this->service->createUser('Super Admin', 'super@test.nl', 'test');
        $super->setRoles(['ROLE_SUPER_ADMIN']);
        $this->addFixture($super, self::SUPER_REFERENCE);

        $admin = $this->service->createUser('Bert Beheer', 'beheer@test.nl', 'test');
        $admin->setRoles(['ROLE_ADMIN']);
        $this->addFixture($admin, self::ADMIN_REFERENCE);

        $coordinator = $this->service->createUser('Cees Coördinator', 'coordinator@test.nl', 'test');
        $coordinator->setRoles(['ROLE_COORDINATE']);
        $coordinator->setPhone($this->phoneUtil->parse('+31401111111', 'NL'));
        $coordinator->setAddress('Hoofdstraat 11a');
        $this->addFixture($coordinator, self::COORDINATOR_REFERENCE);

        $analyst = $this->service->createUser('Antoinette Analist', 'analist@test.nl', 'test');
        $analyst->setRoles(['ROLE_ANALYST']);
        $this->addFixture($analyst, self::ANALYST_REFERENCE);

        $walker1 = $this->service->createUser('Leopold Loper', 'een@test.nl', 'test');
        $walker1->setRoles(['ROLE_WALK']);
        $walker1->setMobile($this->phoneUtil->parse('+31622222222', 'NL'));
        $walker1->setCredited(true);
        $this->addFixture($walker1, self::WALKER1_REFERENCE);

        $walker2 = $this->service->createUser('Sonja Struin', 'twee@test.nl', 'test');
        $walker2->setRoles(['ROLE_WALK']);
        $walker2->setAddress('Binnenplein 58 BS');
        $this->addFixture($walker2, self::WALKER2_REFERENCE);

        $walker3 = $this->service->createUser('Willie Wandel', 'drie@test.nl', 'test');
        $walker3->setRoles(['ROLE_WALK']);
        $walker3->setCredited(true);
        $this->addFixture($walker3, self::WALKER3_REFERENCE);
    }

    /**
     * @param  object $entity
     * @param  string $reference
     * @throws Exception
     */
    private function addFixture($entity, ?string $reference = null)
    {
        $errors = $this->validator->validate($entity);
        if (count($errors) > 0) {
            throw new Exception('exception.user.invalid');
        }

        $this->manager->persist($entity);
        $this->manager->flush();

        if (null !== $reference) {
            $this->addReference($reference, $entity);
        }
    }
}
