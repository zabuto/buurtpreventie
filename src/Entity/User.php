<?php

namespace App\Entity;

use App\Interfaces\LastLoginInterface;
use App\Interfaces\UserTokenInterface;
use App\Interfaces\WalkerInterface;
use App\Traits\SoftDeletable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use Serializable;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User implements LastLoginInterface, UserTokenInterface, UserInterface, WalkerInterface, Serializable
{
    use SoftDeletable;

    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int|null
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=254, unique=true)
     * @Assert\NotBlank()
     * @var string
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=254, unique=true)
     * @Assert\NotNull()
     * @Assert\Email()
     * @var string
     */
    private $email;

    /**
     * @ORM\Column(type="phone_number", nullable=true)
     * @AssertPhoneNumber()
     * @var string|PhoneNumber|null
     */
    private $phone;

    /**
     * @ORM\Column(type="phone_number", nullable=true)
     * @AssertPhoneNumber()
     * @var string|PhoneNumber|null
     */
    private $mobile;

    /**
     * @ORM\Column(type="string", length=254, nullable=true)
     * @var string|null
     */
    private $address;

    /**
     * @ORM\Column(type="string", length=64)
     * @Assert\NotNull()
     * @var string
     */
    private $password;

    /**
     * @ORM\Column(type="json_array")
     * @var array
     */
    private $roles = ['ROLE_USER'];

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Assert\DateTime()
     * @var DateTime|null
     */
    private $lastLogin;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     * @var string|null
     */
    private $token;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Assert\DateTime()
     * @var DateTime|null
     */
    private $tokenValidUntil;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    private $credited = false;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    private $active = true;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime|null
     */
    protected $deletedAt;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param  string $name
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param  string $email
     */
    public function setEmail(?string $email): void
    {
        $this->email = strtolower(trim($email));
    }

    /**
     * @return PhoneNumber|null|string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @param  PhoneNumber|null|string $phone
     */
    public function setPhone($phone): void
    {
        $this->phone = $phone;
    }

    /**
     * @return PhoneNumber|string|null
     */
    public function getMobile()
    {
        return $this->mobile;
    }

    /**
     * @param  PhoneNumber|string|null $mobile
     */
    public function setMobile($mobile): void
    {
        $this->mobile = $mobile;
    }

    /**
     * @return string|null
     */
    public function getAddress(): ?string
    {
        return $this->address;
    }

    /**
     * @param  string|null $address
     */
    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param  string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * @return string|null
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @param  string|null $token
     */
    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    /**
     * @return DateTime|null
     */
    public function getTokenValidUntil(): ?DateTime
    {
        return $this->tokenValidUntil;
    }

    /**
     * @param  DateTime|null $tokenValidUntil
     */
    public function setTokenValidUntil(?DateTime $tokenValidUntil): void
    {
        $this->tokenValidUntil = $tokenValidUntil;
    }

    /**
     * @return bool
     */
    public function isCredited(): bool
    {
        return $this->credited;
    }

    /**
     * @param  bool $credited
     */
    public function setCredited(bool $credited): void
    {
        $this->credited = $credited;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param  bool $active
     */
    public function setActive(bool $active): void
    {
        $this->active = $active;

        if (false === $this->active) {
            $this->password = '';
            $this->token = null;
            $this->tokenValidUntil = null;
        }
    }

    /**
     * @return array
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @param  array $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    /**
     * @return DateTime|null
     */
    public function getLastLogin(): ?DateTime
    {
        return $this->lastLogin;
    }

    /**
     * @param  DateTime $lastLogin
     */
    public function setLastLogin(DateTime $lastLogin): void
    {
        $this->lastLogin = $lastLogin;
    }

    /**
     * @return string
     * @see UserInterface::getUsername()
     */
    public function getUsername()
    {
        return $this->email;
    }

    /**
     * @return string|null
     * @see UserInterface::getSalt()
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * @see UserInterface::eraseCredentials()
     */
    public function eraseCredentials()
    {
    }

    /**
     * @see Serializable::serialize()
     */
    public function serialize()
    {
        return serialize([
            $this->id,
            $this->email,
            $this->password,
            $this->active,
        ]);
    }

    /**
     * @param  string $serialized
     * @see Serializable::unserialize()
     */
    public function unserialize($serialized)
    {
        list (
            $this->id,
            $this->email,
            $this->password,
            $this->active,
            ) = unserialize($serialized, ['allowed_classes' => false]);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
}
