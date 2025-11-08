<?php declare(strict_types=1);

namespace App\Entity;

use App\Dto\Formatter\DateTimeFormatter;
use App\Dto\UserDto;
use App\Repository\UserRepository;
use App\Traits\SoftDeletable;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\TargetClass;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['name'], message: 'user.name-already-in-use')]
#[UniqueEntity(fields: ['email'], message: 'user.email-already-in-use')]
#[Map(target: UserDto::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, Stringable
{
    use SoftDeletable;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[Map(if: false)]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 254, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 254)]
    #[Map(target: 'name')]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 254, unique: true)]
    #[Assert\NotNull]
    #[Assert\Email]
    #[Assert\Length(max: 254)]
    #[Map(target: 'email')]
    private string $email = '';

    #[ORM\Column(type: 'phone_number', nullable: true)]
    #[AssertPhoneNumber]
    #[Map(if: false)]
    private $phone = null;

    #[ORM\Column(type: 'phone_number', nullable: true)]
    #[AssertPhoneNumber(type: [AssertPhoneNumber::MOBILE])]
    #[Map(if: false)]
    private $mobile = null;

    #[ORM\Column(type: 'string', length: 254, nullable: true)]
    #[Assert\Length(max: 254)]
    #[Map(if: false)]
    private ?string $address = null;

    #[ORM\Column(type: 'string')]
    #[Map(if: false)]
    private string $password = '';

    /** @var  array<int, string> */
    #[ORM\Column(type: 'json')]
    #[Map(if: false)]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Map(if: false)]
    private ?DateTimeImmutable $lastLogin = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Map(target: 'token', if: new TargetClass(UserDto::class))]
    private ?string $token = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Map(target: 'valid_until', if: new TargetClass(UserDto::class), transform: [DateTimeFormatter::class, 'format'])]
    private ?DateTimeImmutable $tokenValidUntil = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    #[Map(if: false)]
    private bool $credited = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    #[Map(if: false)]
    private bool $permitted = true;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    #[Map(if: false)]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Map(if: false)]
    protected ?DateTimeImmutable $deletedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = trim((string)$name);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = strtolower(trim((string)$email));
    }

    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @throws NumberParseException
     */
    public function setPhone($phone): void
    {
        if (is_string($phone)) {
            $this->phone = PhoneNumberUtil::getInstance()->parse($phone);
        } else {
            $this->phone = $phone;
        }
    }

    public function getMobile()
    {
        return $this->mobile;
    }

    /**
     * @throws NumberParseException
     */
    public function setMobile($mobile): void
    {
        if (is_string($mobile)) {
            $this->mobile = PhoneNumberUtil::getInstance()->parse($mobile);
        } else {
            $this->mobile = $mobile;
        }
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getTokenValidUntil(): ?DateTimeImmutable
    {
        return $this->tokenValidUntil;
    }

    public function generateToken(int $hours = 4): void
    {
        $this->token = sha1(random_bytes(10));
        $this->tokenValidUntil = new DateTimeImmutable(sprintf('+%s hours', $hours));
    }

    public function resetToken(): void
    {
        $this->token = null;
        $this->tokenValidUntil = null;
    }

    public function isTokenValid(): bool
    {
        $now = new DateTimeImmutable();

        return !(null === $this->tokenValidUntil || $this->tokenValidUntil->format('YmdHis') < $now->format('YmdHis'));
    }

    public function isCredited(): bool
    {
        return $this->credited;
    }

    public function setCredited(bool $credited): void
    {
        $this->credited = $credited;
    }

    public function isPermitted(): bool
    {
        return $this->permitted;
    }

    public function setPermitted(bool $permitted): void
    {
        $this->permitted = $permitted;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;

        if (false === $active) {
            $this->password = '';
            $this->token = null;
            $this->tokenValidUntil = null;
        }
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getLastLogin(): ?DateTimeImmutable
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?DateTimeImmutable $date): void
    {
        $this->lastLogin = $date;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    public function erasePersonalInformation(): void
    {
        $this->password = '';
        $this->token = null;
        $this->tokenValidUntil = null;

        $this->active = false;
        $this->permitted = false;
        $this->address = null;
        $this->phone = null;
        $this->mobile = null;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
