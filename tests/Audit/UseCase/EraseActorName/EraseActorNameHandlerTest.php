<?php

declare(strict_types=1);

namespace App\Tests\Audit\UseCase\EraseActorName;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\ChainLink;
use App\Audit\Infrastructure\HmacChainHasher;
use App\Audit\UseCase\EraseActorName\EraseActorName;
use App\Audit\UseCase\EraseActorName\EraseActorNameHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class EraseActorNameHandlerTest extends TestCase
{
    private RecordingAuditEntryRepository $entries;
    private RecordingAuditTrail $auditTrail;
    private Uuid $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->entries = new RecordingAuditEntryRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->jan = Uuid::v7();
    }

    #[Test]
    public function it_erases_the_name_of_the_member_from_the_log(): void
    {
        $this->handler()(new EraseActorName($this->jan->toRfc4122()));

        $this->assertEquals([$this->jan], $this->entries->erasedActorNames);
    }

    /**
     * The entry that added the member holds the display name in its changed fields; erasing must not leave it there.
     */
    #[Test]
    public function it_erases_the_changed_fields_of_every_entry_about_the_member(): void
    {
        $added = AuditEntry::of(
            new AuditedAction('member.added', 'member', $this->jan, ['displayName' => ['old' => null, 'new' => 'Anna H.']]),
            null,
            null,
            Channel::Console,
            new DateTimeImmutable('2026-11-15T12:00:00+00:00'),
        );
        $added->link(ChainLink::start(), new HmacChainHasher('audit-key'));
        $this->entries->append($added);

        $this->handler()(new EraseActorName($this->jan->toRfc4122()));

        $this->assertSame([[], true], [$added->changes(), $added->hasErasedChanges()]);
    }

    #[Test]
    public function it_records_that_the_name_was_erased(): void
    {
        $this->handler()(new EraseActorName($this->jan->toRfc4122()));

        $this->assertEquals([new AuditedAction('audit.actor_name_erased', 'member', $this->jan)], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_erases_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new EraseActorName($this->jan->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['audit.erase_actor_name', $this->jan]], $authorization->asked);
        $this->assertSame([[], []], [$this->entries->erasedActorNames, $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): EraseActorNameHandler
    {
        return new EraseActorNameHandler(
            $this->entries,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
