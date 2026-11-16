<?php

declare(strict_types=1);

namespace App\Tests\Membership\Infrastructure\Persistence;

use App\Membership\Domain\DisplayNameTaken;
use App\Membership\Domain\EmailAddressTaken;
use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Membership\Infrastructure\Persistence\DoctrineMemberRepository;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\PhoneNumber;
use App\Shared\Domain\Role;
use App\Tests\Database\TestDatabase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Every member gets a unique suffix in its address and names, so rows of other tests in the same database never
 * collide or match.
 */
#[Group('database')]
final class DoctrineMemberRepositoryTest extends KernelTestCase
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
    public function it_brings_a_member_back_with_every_detail(): void
    {
        $jan = $this->member('jan', 'Jan Bakker', 'Jan B.', [Role::Walker, Role::Coordinator]);
        $jan->changeContactConsent(true);
        $this->repository->add($jan);
        $this->entityManager->clear();

        $read = $this->repository->get($jan->id());

        $this->assertSame(
            ['jan' . $this->suffix . '@example.org', 'Jan Bakker ' . $this->suffix, [Role::Walker, Role::Coordinator], '06 1234 5678', true],
            [$read->email()?->value, $read->name(), $read->roles(), $read->phone()?->value, $read->consentsToContactShare()],
        );
    }

    #[Test]
    public function it_reports_an_unknown_member(): void
    {
        $this->expectException(NotFound::class);

        $this->repository->get(Uuid::v7());
    }

    #[Test]
    public function it_gets_the_members_it_knows_and_skips_an_unknown_id(): void
    {
        $jan = $this->stored('jan', 'Jan Bakker', 'Jan B.');
        $piet = $this->stored('piet', 'Piet de Vries', 'Piet V.');
        $this->stored('kees', 'Kees Jansen', 'Kees J.');
        $this->entityManager->clear();

        $found = $this->repository->getMany([$piet->id(), Uuid::v7(), $jan->id()]);

        $this->assertEqualsCanonicalizing([$jan->id()->toRfc4122(), $piet->id()->toRfc4122()], $this->idsOf($found));
    }

    #[Test]
    public function it_gets_no_members_for_no_ids(): void
    {
        $this->assertSame([], $this->repository->getMany([]));
    }

    #[Test]
    public function it_finds_only_the_active_members_who_want_to_hear_about_open_rounds(): void
    {
        $wants = $this->member('jan', 'Jan Bakker', 'Jan B.');
        $wants->changeOpenRoundNotifications(true);
        $this->repository->add($wants);
        $this->stored('piet', 'Piet de Vries', 'Piet V.');
        $inactive = $this->member('kees', 'Kees Jansen', 'Kees J.');
        $inactive->changeOpenRoundNotifications(true);
        $inactive->deactivate();
        $this->repository->add($inactive);
        $this->entityManager->clear();

        $found = $this->repository->activeWithOpenRoundNotifications();

        $this->assertSame([$wants->id()->toRfc4122()], $this->ownIdsOf($found, $wants, $inactive));
    }

    #[Test]
    public function it_finds_the_active_admins_whatever_other_roles_they_have(): void
    {
        $admin = $this->stored('ria', 'Ria Smit', 'Ria S.', [Role::Admin]);
        $adminWhoWalks = $this->stored('jan', 'Jan Bakker', 'Jan B.', [Role::Walker, Role::Admin, Role::Analyst]);
        $walker = $this->stored('piet', 'Piet de Vries', 'Piet V.');
        $inactiveAdmin = $this->member('kees', 'Kees Jansen', 'Kees J.', [Role::Admin]);
        $inactiveAdmin->deactivate();
        $this->repository->add($inactiveAdmin);
        $this->entityManager->clear();

        $found = $this->entityManager->wrapInTransaction(fn(): array => $this->repository->activeAdminsForUpdate());

        $this->assertEqualsCanonicalizing(
            [$admin->id()->toRfc4122(), $adminWhoWalks->id()->toRfc4122()],
            $this->ownIdsOf($found, $admin, $adminWhoWalks, $walker, $inactiveAdmin),
        );
    }

    #[Test]
    public function it_knows_an_address_another_member_has_whatever_its_case(): void
    {
        $this->stored('jan', 'Jan Bakker', 'Jan B.');
        $this->entityManager->clear();

        $taken = $this->repository->isEmailAddressTaken(EmailAddress::fromString('JAN' . $this->suffix . '@Example.org'));

        $this->assertTrue($taken);
    }

    #[Test]
    public function it_knows_the_own_address_of_a_member_as_free_for_that_member(): void
    {
        $jan = $this->stored('jan', 'Jan Bakker', 'Jan B.');
        $this->entityManager->clear();

        $taken = $this->repository->isEmailAddressTaken(EmailAddress::fromString('jan' . $this->suffix . '@example.org'), except: $jan->id());

        $this->assertFalse($taken);
    }

    #[Test]
    public function it_knows_a_display_name_another_member_has_on_its_lookup_key(): void
    {
        $this->stored('jan', 'Jan Bakker', 'José H.');
        $this->entityManager->clear();

        $taken = $this->repository->isDisplayNameTaken(LookupKey::fromName('jose h ' . $this->suffix));

        $this->assertTrue($taken);
    }

    #[Test]
    public function it_knows_the_own_display_name_of_a_member_as_free_for_that_member(): void
    {
        $jan = $this->stored('jan', 'Jan Bakker', 'Jan H.');
        $this->entityManager->clear();

        $taken = $this->repository->isDisplayNameTaken(LookupKey::fromName('Jan H. ' . $this->suffix), except: $jan->id());

        $this->assertFalse($taken);
    }

    #[Test]
    public function it_finds_jose_with_and_without_accent_and_in_capitals(): void
    {
        $jose = $this->stored('jose', 'José Fernandes', 'José F.');
        $this->entityManager->clear();

        $found = [
            $this->ownIdsOf($this->repository->search(LookupKey::fromName('Jose Fernandes ' . $this->suffix)), $jose),
            $this->ownIdsOf($this->repository->search(LookupKey::fromName('JOSE FERNANDES ' . $this->suffix)), $jose),
        ];

        $this->assertSame([[$jose->id()->toRfc4122()], [$jose->id()->toRfc4122()]], $found);
    }

    #[Test]
    public function it_finds_both_members_with_the_same_full_name(): void
    {
        $first = $this->stored('jan', 'Jan Bakker', 'Jan B.');
        $second = $this->stored('jan.b', 'Jan Bakker', 'Jan Ba.');
        $this->entityManager->clear();

        $found = $this->repository->search(LookupKey::fromName('bakker ' . $this->suffix));

        $this->assertEqualsCanonicalizing([$first->id()->toRfc4122(), $second->id()->toRfc4122()], $this->ownIdsOf($found, $first, $second));
    }

    #[Test]
    public function it_finds_a_member_by_part_of_the_display_name(): void
    {
        $jan = $this->stored('jan', 'Johannes Bakker', 'Jantje B.');
        $this->entityManager->clear();

        $found = $this->repository->search(LookupKey::fromName('tje b ' . $this->suffix));

        $this->assertSame([$jan->id()->toRfc4122()], $this->ownIdsOf($found, $jan));
    }

    /**
     * Punctuation in a search term counts as a space, so a percent sign is never a wildcard: "ab%cd" looks for
     * "ab cd", which "abXXcd" does not contain.
     */
    #[Test]
    public function it_takes_a_percent_sign_in_the_term_for_no_wildcard(): void
    {
        $adriaan = $this->stored('adriaan', 'Adriaan' . $this->suffix . 'Smit', 'Adriaan S.');
        $this->entityManager->clear();

        $found = $this->repository->search(LookupKey::fromName('adriaan' . $this->suffix . '%smit'));

        $this->assertSame([], $this->ownIdsOf($found, $adriaan));
    }

    #[Test]
    public function it_lists_every_member_by_display_name_without_a_term(): void
    {
        $piet = $this->stored('piet', 'Piet de Vries', 'B Piet');
        $jan = $this->stored('jan', 'Jan Bakker', 'A Jan');
        $this->entityManager->clear();

        $found = $this->repository->search();

        $this->assertSame([$jan->id()->toRfc4122(), $piet->id()->toRfc4122()], $this->ownIdsOf($found, $piet, $jan));
    }

    #[Test]
    public function it_leaves_an_erased_member_out_of_the_search(): void
    {
        $erased = $this->member('jan', 'Jan Bakker', 'Jan B.');
        $erased->erase(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));
        $this->repository->add($erased);
        $this->entityManager->clear();

        $found = $this->repository->search();

        $this->assertSame([], $this->ownIdsOf($found, $erased));
    }

    #[Test]
    public function it_turns_an_address_that_differs_only_in_case_into_email_address_taken(): void
    {
        $this->stored('anke', 'Anke de Boer', 'Anke B.');
        $twin = Member::register(new MemberDetails(
            EmailAddress::fromString('Anke' . $this->suffix . '@Example.org'),
            'Anke Visser',
            'Anke V. ' . $this->suffix,
        ));

        $this->expectException(EmailAddressTaken::class);

        $this->repository->add($twin);
    }

    #[Test]
    public function it_turns_a_display_name_on_the_same_lookup_key_into_display_name_taken(): void
    {
        $this->stored('jan', 'Jan Hendriks', 'Jan H.');
        $twin = $this->member('jan.h', 'Jan Hoekstra', 'jan h');

        $this->expectException(DisplayNameTaken::class);

        $this->repository->add($twin);
    }

    #[Test]
    public function it_turns_a_changed_address_another_member_has_into_email_address_taken(): void
    {
        $this->stored('jan', 'Jan Bakker', 'Jan B.');
        $piet = $this->stored('piet', 'Piet de Vries', 'Piet V.');
        $piet->change(new MemberDetails(
            EmailAddress::fromString('jan' . $this->suffix . '@example.org'),
            'Piet de Vries',
            'Piet V. ' . $this->suffix,
        ));

        $this->expectException(EmailAddressTaken::class);

        $this->repository->update($piet);
    }

    #[Test]
    public function it_keeps_two_erased_members_without_display_name(): void
    {
        $first = $this->member('jan', 'Jan Bakker', 'Jan B.');
        $second = $this->member('piet', 'Piet de Vries', 'Piet V.');
        $first->erase(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));
        $second->erase(new DateTimeImmutable('2026-11-15T12:00:00+00:00'));

        $this->repository->add($first);
        $this->repository->add($second);

        $this->entityManager->clear();
        $this->assertSame([null, null], [$this->repository->get($first->id())->displayName(), $this->repository->get($second->id())->displayName()]);
    }

    /**
     * @param list<Role> $roles
     */
    private function member(string $local, string $name, string $displayName, array $roles = [Role::Walker]): Member
    {
        $member = Member::register(new MemberDetails(
            EmailAddress::fromString($local . $this->suffix . '@example.org'),
            $name . ' ' . $this->suffix,
            $displayName . ' ' . $this->suffix,
            PhoneNumber::fromString('06 1234 5678'),
            'Dorpsstraat 1',
        ));
        $member->changeRoles($roles);

        return $member;
    }

    /**
     * @param list<Role> $roles
     */
    private function stored(string $local, string $name, string $displayName, array $roles = [Role::Walker]): Member
    {
        $member = $this->member($local, $name, $displayName, $roles);
        $this->repository->add($member);

        return $member;
    }

    /**
     * @param list<Member> $found
     *
     * @return list<string>
     */
    private function idsOf(array $found): array
    {
        return \array_map(static fn(Member $member): string => $member->id()->toRfc4122(), $found);
    }

    /**
     * Only the members this test made, in the order found, so rows of other tests cannot interfere.
     *
     * @param list<Member> $found
     *
     * @return list<string>
     */
    private function ownIdsOf(array $found, Member ...$own): array
    {
        $ownIds = $this->idsOf(\array_values($own));

        return \array_values(\array_filter($this->idsOf($found), static fn(string $id): bool => \in_array($id, $ownIds, strict: true)));
    }
}
