<?php

declare(strict_types=1);

namespace App\Tests\Audit\Domain;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\ChainLink;
use App\Audit\Infrastructure\HmacChainHasher;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AuditEntryChainTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    #[Test]
    public function it_takes_the_position_after_the_previous_link(): void
    {
        $entry = $this->entry();

        $link = $entry->link(new ChainLink(41, \str_repeat('p', 32)), new HmacChainHasher('audit-key'));

        $this->assertSame([42, 42, 32], [$link->position, $entry->chainPosition(), \strlen($link->hash)]);
    }

    #[Test]
    public function it_links_to_the_link_it_was_linked_after(): void
    {
        $hasher = new HmacChainHasher('audit-key');
        $previous = new ChainLink(41, \str_repeat('p', 32));
        $entry = $this->entry();
        $entry->link($previous, $hasher);

        $links = $entry->linksTo($previous, $hasher);

        $this->assertTrue($links);
    }

    /**
     * The previous hash is part of the hash, so an entry cannot be moved behind another one.
     */
    #[Test]
    public function it_does_not_link_to_another_previous_hash(): void
    {
        $hasher = new HmacChainHasher('audit-key');
        $entry = $this->entry();
        $entry->link(new ChainLink(41, \str_repeat('p', 32)), $hasher);

        $links = $entry->linksTo(new ChainLink(41, \str_repeat('q', 32)), $hasher);

        $this->assertFalse($links);
    }

    #[Test]
    public function it_does_not_link_under_another_key(): void
    {
        $previous = new ChainLink(41, \str_repeat('p', 32));
        $entry = $this->entry();
        $entry->link($previous, new HmacChainHasher('audit-key'));

        $links = $entry->linksTo($previous, new HmacChainHasher('other-key'));

        $this->assertFalse($links);
    }

    #[Test]
    public function it_has_intact_changes_while_they_match_their_own_hash(): void
    {
        $hasher = new HmacChainHasher('audit-key');
        $entry = $this->entry(['displayName' => ['old' => null, 'new' => 'Anna H.']]);
        $entry->link(ChainLink::start(), $hasher);

        $intact = $entry->hasIntactChanges($hasher);

        $this->assertTrue($intact);
    }

    /**
     * Erasing removes the changed fields but leaves their hash, which the chain covers: the entry stays a valid link,
     * and nothing in the database tells the erased content.
     */
    #[Test]
    public function it_stays_a_valid_link_with_intact_changes_once_its_changes_are_erased(): void
    {
        $hasher = new HmacChainHasher('audit-key');
        $entry = $this->entry(['displayName' => ['old' => null, 'new' => 'Anna H.']]);
        $entry->link(ChainLink::start(), $hasher);

        $entry->eraseChanges();

        $this->assertSame(
            [[], true, true, true],
            [$entry->changes(), $entry->hasErasedChanges(), $entry->hasIntactChanges($hasher), $entry->linksTo(ChainLink::start(), $hasher)],
        );
    }

    /**
     * Erasing removes the actor's name, so the name stays outside the hash.
     */
    #[Test]
    public function it_stays_a_valid_link_once_the_name_of_its_actor_is_erased(): void
    {
        $hasher = new HmacChainHasher('audit-key');
        $entry = $this->entry();
        $entry->link(ChainLink::start(), $hasher);

        $entry->eraseActorName();

        $this->assertTrue($entry->linksTo(ChainLink::start(), $hasher));
    }

    #[Test]
    public function it_has_no_erased_changes_when_it_never_had_any(): void
    {
        $hasher = new HmacChainHasher('audit-key');
        $entry = $this->entry();
        $entry->link(ChainLink::start(), $hasher);

        $erased = $entry->hasErasedChanges();

        $this->assertFalse($erased);
    }

    #[Test]
    public function it_refuses_a_second_place_in_the_chain(): void
    {
        $hasher = new HmacChainHasher('audit-key');
        $entry = $this->entry();
        $entry->link(ChainLink::start(), $hasher);

        $this->expectException(LogicException::class);

        $entry->link(new ChainLink(7, \str_repeat('p', 32)), $hasher);
    }

    #[Test]
    public function it_has_no_link_before_it_is_linked(): void
    {
        $entry = $this->entry();

        $this->assertSame([null, null], [$entry->chainPosition(), $entry->chainLink()]);
    }

    /**
     * @param array<string, array{old: scalar|null, new: scalar|null}> $changes
     */
    private function entry(array $changes = []): AuditEntry
    {
        return AuditEntry::of(
            new AuditedAction('member.added', 'member', Uuid::v7(), $changes),
            Uuid::v7(),
            'Anna H.',
            Channel::Web,
            new DateTimeImmutable(self::NOW),
        );
    }
}
