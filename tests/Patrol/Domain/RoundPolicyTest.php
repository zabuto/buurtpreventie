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
    public function it_lets_the_creator_change_a_round_while_walking_alone(): void
    {
        $creator = new Actor(Uuid::v7(), [Role::Walker]);
        $round = RoundFixture::withWalkers($creator->memberId, 1);

        $this->assertTrue(new RoundPolicy()->mayChange($creator, $round));
    }

    #[Test]
    public function it_lets_the_creator_change_a_round_until_a_buddy_joins(): void
    {
        $creator = new Actor(Uuid::v7(), [Role::Walker]);
        $round = RoundFixture::withWalkers($creator->memberId, 1);

        $round->signUp(Uuid::v7(), new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->assertFalse(new RoundPolicy()->mayChange($creator, $round));
    }

    #[Test]
    public function it_refuses_the_creator_a_change_once_another_walker_took_over(): void
    {
        $creator = new Actor(Uuid::v7(), [Role::Walker]);
        $round = RoundFixture::withWalkers($creator->memberId, 1);
        $round->signUp(Uuid::v7(), new DateTimeImmutable(RoundFixture::BEFORE_START));

        $round->withdraw($creator->memberId, new DateTimeImmutable(RoundFixture::BEFORE_START));

        $this->assertFalse(new RoundPolicy()->mayChange($creator, $round));
    }

    /**
     * @return iterable<string, array{list<Role>, bool}>
     */
    public static function othersThanTheCreator(): iterable
    {
        yield 'coordinator' => [[Role::Coordinator], true];
        yield 'walker' => [[Role::Walker], false];
        yield 'admin' => [[Role::Admin], false];
    }

    /**
     * @param list<Role> $roles
     */
    #[DataProvider('othersThanTheCreator')]
    #[Test]
    public function it_lets_only_a_coordinator_change_a_round_of_someone_else(array $roles, bool $expected): void
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), 2);

        $this->assertSame($expected, new RoundPolicy()->mayChange(new Actor(Uuid::v7(), $roles), $round));
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
        $report = $round->report($author, Outcome::create('Niets te melden', OutcomeKind::NothingToReport), null, new DateTimeImmutable(self::AFTER_THE_ROUND));
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
}
