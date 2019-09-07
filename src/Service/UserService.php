<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\TokenInvalidException;
use App\Exception\UserInvalidException;
use App\Interfaces\UserTokenInterface;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * UserService
 */
class UserService
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $encoder;

    /**
     * @param  EntityManagerInterface       $em
     * @param  ValidatorInterface           $validator
     * @param  UserPasswordEncoderInterface $encoder
     */
    public function __construct(EntityManagerInterface $em, ValidatorInterface $validator, UserPasswordEncoderInterface $encoder)
    {
        $this->em = $em;
        $this->validator = $validator;
        $this->encoder = $encoder;
    }

    /**
     * @return array
     */
    public function getMemberRoles()
    {
        return ['ROLE_WALK', 'ROLE_COORDINATE'];
    }

    /**
     * @return User
     * @throws Exception
     */
    public function initUser()
    {
        $user = new User();
        $user->setPassword('');

        if ($user instanceof UserTokenInterface) {
            $date = new DateTime('+12 hours');
            $user->setToken(sha1(random_bytes(10)));
            $user->setTokenValidUntil($date);
        }

        return $user;
    }

    /**
     * @param  User $user
     * @throws UserInvalidException
     */
    public function saveUser(User $user)
    {
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            throw new UserInvalidException('exception.user.invalid');
        }

        $this->em->persist($user);
        $this->em->flush();
    }

    /**
     * @param  string $name
     * @param  string $email
     * @param  string $plainPassword
     * @return User
     * @throws UserInvalidException
     */
    public function createUser(string $name, string $email, string $plainPassword): User
    {
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);

        $encoded = $this->encoder->encodePassword($user, $plainPassword);
        $user->setPassword($encoded);

        $this->saveUser($user);

        return $user;
    }

    /**
     * @param  mixed $token
     * @return User
     * @throws TokenInvalidException
     */
    public function getUserByToken($token)
    {
        /** @var User $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['token' => $token]);
        if (null === $user) {
            throw new TokenInvalidException('exception.token.invalid');
        }

        if (!($user instanceof UserTokenInterface)) {
            throw new TokenInvalidException('exception.token.unavailable');
        }

        $now = new DateTime();
        $valid = $user->getTokenValidUntil();
        if (null === $valid || $valid->format('YmdHis') < $now->format('YmdHis')) {
            throw new TokenInvalidException('exception.token.expired');
        }

        return $user;
    }

    /**
     * @param  User   $user
     * @param  string $plainPassword
     * @throws TokenInvalidException
     */
    public function saveTokenUser(User $user, string $plainPassword)
    {
        if (!($user instanceof UserTokenInterface)) {
            throw new TokenInvalidException('exception.token.unavailable');
        }

        $encoded = $this->encoder->encodePassword($user, $plainPassword);
        $user->setPassword($encoded);

        $user->setToken(null);
        $user->setTokenValidUntil(null);

        $this->em->flush();
    }

    /**
     * @param  User   $user
     * @param  string $plainPassword
     * @return bool
     */
    public function checkPassword(User $user, string $plainPassword)
    {
        return $this->encoder->isPasswordValid($user, $plainPassword);
    }

    /**
     * @param  User   $user
     * @param  string $plainPassword
     * @throws UserInvalidException
     */
    public function updatePassword(User $user, string $plainPassword)
    {
        $encoded = $this->encoder->encodePassword($user, $plainPassword);
        $user->setPassword($encoded);

        $this->saveUser($user);
    }

    /**
     * @param  string $email
     * @return User|null
     * @throws Exception
     */
    public function resetPassword($email)
    {
        /** @var User $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (null === $user) {
            return null;
        }

        if (!($user instanceof UserTokenInterface)) {
            return null;
        }

        $date = new DateTime('+4 hours');
        $user->setToken(sha1(random_bytes(10)));
        $user->setTokenValidUntil($date);

        $this->em->flush();

        return $user;
    }
}
