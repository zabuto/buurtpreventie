<?php

declare(strict_types=1);

namespace App\Tests\Audit\Domain;

use App\Audit\Domain\AuditEntry;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AuditEntryTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    #[Test]
    public function it_keeps_who_did_what_when_and_how(): void
    {
        $actor = Uuid::v7();
        $round = Uuid::v7();

        $entry = AuditEntry::of(
            new AuditedAction('round.changed', 'round', $round, ['startsAt' => ['old' => '2026-11-20 22:00', 'new' => '2026-11-20 23:00']]),
            $actor,
            'Jan B.',
            Channel::Api,
            new DateTimeImmutable(self::NOW),
        );

        $this->assertEquals(
            [new DateTimeImmutable(self::NOW), $actor, 'Jan B.', 'round.changed', 'round', $round, Channel::Api],
            [
                $entry->occurredAt(),
                $entry->actorId(),
                $entry->actorName(),
                $entry->action(),
                $entry->subjectType(),
                $entry->subjectId(),
                $entry->channel(),
            ],
        );
        $this->assertSame(['startsAt' => ['old' => '2026-11-20 22:00', 'new' => '2026-11-20 23:00']], $entry->changes());
    }

    #[Test]
    public function it_has_no_actor_when_the_system_acts(): void
    {
        $entry = AuditEntry::of(new AuditedAction('participation.reminded', 'round', Uuid::v7()), null, null, Channel::Cron, new DateTimeImmutable(self::NOW));

        $this->assertTrue($entry->isBySystem());
    }

    /**
     * An erased member keeps the id, so the entry shows that a member acted, as "removed member".
     */
    #[Test]
    public function it_keeps_the_actor_without_a_name_once_the_name_is_erased(): void
    {
        $entry = AuditEntry::of(new AuditedAction('round.created', 'round', Uuid::v7()), Uuid::v7(), 'Jan B.', Channel::Web, new DateTimeImmutable(self::NOW));

        $entry->eraseActorName();

        $this->assertSame([null, false], [$entry->actorName(), $entry->isBySystem()]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function secretFields(): iterable
    {
        yield 'a password' => ['password'];
        yield 'a password hash' => ['passwordHash'];
        yield 'a token' => ['apiToken'];
        yield 'a secret' => ['clientSecret'];
        yield 'any hash' => ['tokenHash'];
    }

    #[Test]
    #[DataProvider('secretFields')]
    public function it_refuses_a_change_to_a_field_that_holds_a_secret(string $field): void
    {
        $this->expectExceptionObject(new InvalidArgumentException(\sprintf('The audit log never holds the field "%s".', $field)));

        AuditEntry::of(
            new AuditedAction('account.changed', 'account', Uuid::v7(), [$field => ['old' => 'a', 'new' => 'b']]),
            null,
            null,
            Channel::Console,
            new DateTimeImmutable(self::NOW),
        );
    }

    #[Test]
    public function it_accepts_a_change_to_an_ordinary_field(): void
    {
        $entry = AuditEntry::of(
            new AuditedAction('member.changed', 'member', Uuid::v7(), ['displayName' => ['old' => 'Jan B.', 'new' => 'Jan Ba.']]),
            null,
            null,
            Channel::Console,
            new DateTimeImmutable(self::NOW),
        );

        $this->assertSame(['displayName' => ['old' => 'Jan B.', 'new' => 'Jan Ba.']], $entry->changes());
    }

    #[Test]
    public function it_is_about_another_member_when_its_subject_is_a_member_other_than_the_one_reading(): void
    {
        $reader = Uuid::v7();

        $entry = AuditEntry::of(
            new AuditedAction('member.roles_changed', 'member', Uuid::v7()),
            $reader,
            'Jan B.',
            Channel::Web,
            new DateTimeImmutable(self::NOW),
        );

        $this->assertTrue($entry->isAboutAnotherMemberThan($reader));
    }

    #[Test]
    public function it_is_not_about_another_member_when_its_subject_is_the_reader_or_no_member(): void
    {
        $reader = Uuid::v7();

        $own = AuditEntry::of(
            new AuditedAction('member.consent_changed', 'member', $reader),
            $reader,
            'Jan B.',
            Channel::Web,
            new DateTimeImmutable(self::NOW),
        );
        $round = AuditEntry::of(new AuditedAction('round.created', 'round', Uuid::v7()), $reader, 'Jan B.', Channel::Web, new DateTimeImmutable(self::NOW));

        $this->assertSame([false, false], [$own->isAboutAnotherMemberThan($reader), $round->isAboutAnotherMemberThan($reader)]);
    }
}
