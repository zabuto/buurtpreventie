<?php

declare(strict_types=1);

namespace App\Tests\Membership\Infrastructure\Persistence;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Membership\Infrastructure\Persistence\DoctrineMemberRepository;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\PhoneNumber;
use App\Tests\Database\TestDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The lookup the login uses. Every member gets a unique suffix in its address, so rows of other tests in the same
 * database never match.
 */
#[Group('database')]
final class DoctrineMemberRepositoryOfEmailTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineMemberRepository $repository;
    private string $suffix;

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineMemberRepository($entityManager);
        $this->suffix = \substr(\bin2hex(\random_bytes(6)), 0, 12);
    }

    #[Test]
    public function it_finds_a_member_by_an_address_typed_in_capitals(): void
    {
        $jan = $this->stored('jan', 'Jan Bakker');
        $this->entityManager->clear();

        $found = $this->repository->ofEmail(EmailAddress::fromString('JAN' . $this->suffix . '@Example.org'));

        $this->assertEquals($jan->id(), $found?->id());
    }

    /**
     * The column compares bytes, so a login as jose@ never reaches the account of josé@.
     */
    #[Test]
    public function it_finds_nobody_for_the_address_without_its_accent(): void
    {
        $this->stored('josé', 'José Bakker');
        $this->entityManager->clear();

        $found = $this->repository->ofEmail(EmailAddress::fromString('jose' . $this->suffix . '@example.org'));

        $this->assertNull($found);
    }

    private function stored(string $local, string $name): Member
    {
        $member = Member::register(new MemberDetails(
            EmailAddress::fromString($local . $this->suffix . '@example.org'),
            $name . ' ' . $this->suffix,
            $name . ' ' . $this->suffix,
            PhoneNumber::fromString('06 1234 5678'),
            'Dorpsstraat 1',
        ));
        $this->repository->add($member);

        return $member;
    }
}
