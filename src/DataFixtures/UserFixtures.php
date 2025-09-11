<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserFixtures extends Fixture
{
    public const string SUPER_REFERENCE = 'user-super';
    public const string ADMIN_REFERENCE = 'user-admin';
    public const string COORDINATOR_REFERENCE = 'user-coordinator';
    public const string ANALYST_REFERENCE = 'user-analyst';
    public const string WALKER1_REFERENCE = 'user-walker-1';
    public const string WALKER2_REFERENCE = 'user-walker-2';
    public const string WALKER3_REFERENCE = 'user-walker-3';
    public const string WALKER4_REFERENCE = 'user-walker-4';

    protected ObjectManager $manager;

    public function __construct(
        private readonly ValidatorInterface          $validator,
        private readonly UserPasswordHasherInterface $passwordHasher,
    )
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;

        $super = $this->createUser('Super Admin', 'super@test.nl', 'test');
        $super->setRoles(['ROLE_SUPER_ADMIN']);
        $this->addFixture($super, self::SUPER_REFERENCE);

        $admin = $this->createUser('Bert Beheer', 'beheer@test.nl', 'test');
        $admin->setRoles(['ROLE_ADMIN']);
        $this->addFixture($admin, self::ADMIN_REFERENCE);

        $coordinator = $this->createUser('Cees Coördinator', 'coordinator@test.nl', 'test');
        $coordinator->setRoles(['ROLE_COORDINATE']);
        $coordinator->setPhone('+31401111111');
        $coordinator->setAddress('Hoofdstraat 11a');
        $this->addFixture($coordinator, self::COORDINATOR_REFERENCE);

        $analyst = $this->createUser('Antoinette Analist', 'analist@test.nl', 'test');
        $analyst->setRoles(['ROLE_ANALYST']);
        $this->addFixture($analyst, self::ANALYST_REFERENCE);

        $walker1 = $this->createUser('Leopold Loper', 'een@test.nl', 'test');
        $walker1->setRoles(['ROLE_WALK']);
        $walker1->setMobile('+31622222222');
        $walker1->setCredited(true);
        $this->addFixture($walker1, self::WALKER1_REFERENCE);

        $walker2 = $this->createUser('Sonja Struin', 'twee@test.nl', 'test');
        $walker2->setRoles(['ROLE_WALK']);
        $walker2->setAddress('Binnenplein 58 BS');
        $walker2->setMobile('+31633333333');
        $walker2->setPermitted(false);
        $this->addFixture($walker2, self::WALKER2_REFERENCE);

        $walker3 = $this->createUser('Willie Wandel', 'drie@test.nl', 'test');
        $walker3->setRoles(['ROLE_WALK']);
        $walker3->setCredited(true);
        $this->addFixture($walker3, self::WALKER3_REFERENCE);

        $walker4 = $this->createUser('Gerrie Gestopt', 'vier@test.nl', 'test');
        $walker4->setRoles(['ROLE_WALK']);
        $walker4->setDeletedAt(new DateTimeImmutable(date('Y-01-01')));
        $walker4->setPermitted(false);
        $walker4->setPassword('');
        $walker4->setActive(false);
        $this->addFixture($walker4, self::WALKER4_REFERENCE);
    }

    private function createUser(string $name, string $email, string $plaintextPassword): User
    {
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);

        $hashedNewPassword = $this->passwordHasher->hashPassword($user, $plaintextPassword);
        $user->setPassword($hashedNewPassword);

        return $user;
    }

    private function addFixture(object $entity, ?string $reference = null): void
    {
        $errors = $this->validator->validate($entity);
        if (count($errors) > 0) {
            throw new LogicException('exception.user.invalid');
        }

        $this->manager->persist($entity);
        $this->manager->flush();

        if (null !== $reference) {
            $this->addReference($reference, $entity);
        }
    }
}
