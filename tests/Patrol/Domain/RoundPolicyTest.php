<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Domain;

use App\Patrol\Domain\Outcome;
use App\Patrol\Domain\OutcomeKind;
use App\Patrol\Domain\RoundComment;
use App\Patrol\Domain\RoundPolicy;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RoundPolicyTest extends TestCase
{
    private const string AFTER_THE_ROUND = '2026-11-21T08:00:00+00:00';

    #[Test]
    public function it_lets_the_owner_change_a_round_while_walking_alone(): void
    {
        $owner = new Actor(Uuid::v7(), [Role::Walker]);
        $round = RoundFixture::withWalkers($owner->memberId, 1);

        $this->assertTrue(new RoundPolicy()->mayChange($owner, $round));
    }

    #[Test]
    public function it_lets_the_owner_change_a_round_until_a_buddy_joins(): void
    {
        $owner = new Actor(Uuid::v7(), [Role::Walker]);
        $round = RoundFixture::withWalkers($owner->memberId, 1);

        $round->signUp(Uuid::v7(), new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->assertFalse(new RoundPolicy()->mayChange($owner, $round));
    }

    #[Test]
    public function it_lets_the_walker_left_alone_change_the_round(): void
    {
        $owner = Uuid::v7();
        $buddy = new Actor(Uuid::v7(), [Role::Walker]);
        $round = RoundFixture::withWalkers($owner, 1);
        $round->signUp($buddy->memberId, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $round->withdraw($owner, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->assertTrue(new RoundPolicy()->mayChange($buddy, $round));
    }

    /**
     * @return iterable<string, array{list<Role>, bool}>
     */
    public static function othersThanTheOwner(): iterable
    {
        yield 'coordinator' => [[Role::Coordinator], true];
        yield 'walker' => [[Role::Walker], false];
        yield 'analyst' => [[Role::Analyst], false];
        yield 'admin' => [[Role::Admin], false];
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('othersThanTheOwner')]
    #[Test]
    public function it_lets_only_a_coordinator_change_a_round_of_someone_else(array $roles, bool $expected): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 1);

        $this->assertSame($expected, new RoundPolicy()->mayChange(new Actor(Uuid::v7(), $roles), $round));
    }

    /**
     * @return iterable<string, array{bool, list<Role>, bool}>
     */
    public static function signUps(): iterable
    {
        yield 'a walker for themselves' => [true, [Role::Walker], true];
        yield 'a member without the walker role for themselves' => [true, [Role::Analyst], false];
        yield 'a coordinator for themselves without the walker role' => [true, [Role::Coordinator], false];
        yield 'a coordinator for someone else' => [false, [Role::Coordinator], true];
        yield 'a walker for someone else' => [false, [Role::Walker], false];
        yield 'an admin for someone else' => [false, [Role::Admin], false];
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('signUps')]
    #[Test]
    public function it_lets_a_walker_sign_up_and_a_coordinator_sign_up_others(bool $self, array $roles, bool $expected): void
    {
        $actor = new Actor(Uuid::v7(), $roles);

        $allowed = new RoundPolicy()->maySignUp($actor, $self ? $actor->memberId : Uuid::v7());

        $this->assertSame($expected, $allowed);
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('signUps')]
    #[Test]
    public function it_lets_a_walker_create_a_round_and_a_coordinator_create_one_for_others(bool $self, array $roles, bool $expected): void
    {
        $actor = new Actor(Uuid::v7(), $roles);

        $allowed = new RoundPolicy()->mayCreate($actor, $self ? $actor->memberId : Uuid::v7());

        $this->assertSame($expected, $allowed);
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('signUps')]
    #[Test]
    public function it_lets_a_walker_withdraw_and_a_coordinator_remove_others(bool $self, array $roles, bool $expected): void
    {
        $actor = new Actor(Uuid::v7(), $roles);

        $allowed = new RoundPolicy()->mayWithdraw($actor, $self ? $actor->memberId : Uuid::v7());

        $this->assertSame($expected, $allowed);
    }

    /**
     * @return iterable<string, array{bool, list<Role>, bool}>
     */
    public static function reporters(): iterable
    {
        yield 'a walker of the round' => [true, [Role::Walker], true];
        yield 'an analyst who walked the round' => [true, [Role::Analyst], true];
        yield 'a coordinator who did not walk' => [false, [Role::Coordinator], true];
        yield 'a walker of another round' => [false, [Role::Walker], false];
        yield 'an admin who did not walk' => [false, [Role::Admin], false];
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('reporters')]
    #[Test]
    public function it_lets_a_walker_of_the_round_or_a_coordinator_report(bool $walked, array $roles, bool $expected): void
    {
        $actor = new Actor(Uuid::v7(), $roles);
        $round = RoundFixture::withWalkers($walked ? $actor->memberId : Uuid::v7(), 2);

        $this->assertSame($expected, new RoundPolicy()->mayReport($actor, $round));
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('reporters')]
    #[Test]
    public function it_lets_a_walker_of_the_round_or_a_coordinator_comment(bool $walks, array $roles, bool $expected): void
    {
        $actor = new Actor(Uuid::v7(), $roles);
        $round = RoundFixture::withWalkers($walks ? $actor->memberId : Uuid::v7(), 1);

        $this->assertSame($expected, new RoundPolicy()->mayComment($actor, $round));
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('reporters')]
    #[Test]
    public function it_shows_phone_numbers_to_the_walkers_of_the_round_and_coordinators(bool $walks, array $roles, bool $expected): void
    {
        $actor = new Actor(Uuid::v7(), $roles);
        $round = RoundFixture::withWalkers($walks ? $actor->memberId : Uuid::v7(), 2);

        $this->assertSame($expected, new RoundPolicy()->maySeeWalkerPhones($actor, $round));
    }

    /**
     * @return iterable<string, array{bool, list<Role>, bool}>
     */
    public static function deleters(): iterable
    {
        yield 'the author' => [true, [Role::Walker], true];
        yield 'a coordinator' => [false, [Role::Coordinator], true];
        yield 'another walker' => [false, [Role::Walker], false];
        yield 'an admin' => [false, [Role::Admin], false];
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('deleters')]
    #[Test]
    public function it_lets_only_the_author_or_a_coordinator_delete_a_report(bool $isAuthor, array $roles, bool $expected): void
    {
        $author = Uuid::v7();
        $round = RoundFixture::withWalkers($author, 2);
        $report = $round->submitReport(
            $author,
            Outcome::create('Niets te melden', OutcomeKind::NothingToReport),
            null,
            new DateTimeImmutable(self::AFTER_THE_ROUND),
        );
        $actor = new Actor($isAuthor ? $author : Uuid::v7(), $roles);

        $this->assertSame($expected, new RoundPolicy()->mayDeleteReport($actor, $report));
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('deleters')]
    #[Test]
    public function it_lets_only_the_author_or_a_coordinator_delete_a_comment(bool $isAuthor, array $roles, bool $expected): void
    {
        $author = Uuid::v7();
        $comment = RoundComment::post(RoundFixture::withWalkers($author, 1), $author, 'Ik ben iets later.', new DateTimeImmutable(RoundFixture::CREATED_AT));
        $actor = new Actor($isAuthor ? $author : Uuid::v7(), $roles);

        $this->assertSame($expected, new RoundPolicy()->mayDeleteComment($actor, $comment));
    }

    /**
     * @return iterable<string, array{list<Role>, bool}>
     */
    public static function catalogueManagers(): iterable
    {
        yield 'admin' => [[Role::Admin], true];
        yield 'coordinator' => [[Role::Coordinator], false];
        yield 'walker' => [[Role::Walker], false];
        yield 'analyst' => [[Role::Analyst], false];
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('catalogueManagers')]
    #[Test]
    public function it_lets_only_an_admin_manage_the_catalogues(array $roles, bool $expected): void
    {
        $this->assertSame($expected, new RoundPolicy()->mayManageCatalogue(new Actor(Uuid::v7(), $roles)));
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('catalogueManagers')]
    #[Test]
    public function it_lets_only_an_admin_withdraw_a_member_from_upcoming_rounds(array $roles, bool $expected): void
    {
        $this->assertSame($expected, new RoundPolicy()->mayWithdrawFromUpcomingRounds(new Actor(Uuid::v7(), $roles)));
    }
}
