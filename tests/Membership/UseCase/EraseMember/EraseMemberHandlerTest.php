<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\EraseMember;

use App\Membership\Domain\Member;
use App\Membership\Domain\UniqueMemberDetails;
use App\Membership\UseCase\AddMember\AddMember;
use App\Membership\UseCase\AddMember\AddMemberHandler;
use App\Membership\UseCase\EraseMember\EraseMember;
use App\Membership\UseCase\EraseMember\EraseMemberHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class EraseMemberHandlerTest extends TestCase
{
    private const string NOW = '2026-11-15T12:00:00+00:00';

    private InMemoryMemberRepository $members;
    private RecordingAuditTrail $auditTrail;
    private Member $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
        $this->auditTrail = new RecordingAuditTrail();
        $this->jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
    }

    #[Test]
    public function it_erases_the_member_at_the_current_time(): void
    {
        $this->handler()(new EraseMember($this->jan->id()->toRfc4122()));

        $this->assertSame([null, null], [$this->jan->email(), $this->jan->displayName()]);
        $this->assertEquals(new DateTimeImmutable(self::NOW), $this->jan->erasedAt());
    }

    #[Test]
    public function it_records_the_erasure_without_personal_data(): void
    {
        $this->handler()(new EraseMember($this->jan->id()->toRfc4122()));

        $this->assertEquals([new AuditedAction('member.erased', 'member', $this->jan->id())], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_lets_two_erased_members_exist_side_by_side_without_a_display_name(): void
    {
        $piet = MemberFixture::stored($this->members, 'piet@example.org', 'Piet Jansen', 'Piet J.');
        $handler = $this->handler();
        $handler(new EraseMember($this->jan->id()->toRfc4122()));

        $handler(new EraseMember($piet->id()->toRfc4122()));

        $this->assertSame([null, null], [$this->jan->displayNameKey(), $piet->displayNameKey()]);
    }

    #[Test]
    public function it_frees_the_address_and_display_name_for_a_new_member(): void
    {
        $this->handler()(new EraseMember($this->jan->id()->toRfc4122()));
        $addMember = new AddMemberHandler(
            $this->members,
            new UniqueMemberDetails($this->members),
            FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );

        $addMember(new AddMember(email: 'jan@example.org', name: 'Jan Bakker', displayName: 'Jan B.'));

        $this->assertCount(2, $this->members->all());
    }

    #[Test]
    public function it_erases_nothing_when_access_is_denied(): void
    {
        $refusal = null;
        try {
            $this->handler(FakeAuthorization::refusing())(new EraseMember($this->jan->id()->toRfc4122()));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([false, []], [$this->jan->isErased(), $this->auditTrail->recorded]);
    }

    private function handler(?FakeAuthorization $authorization = null): EraseMemberHandler
    {
        return new EraseMemberHandler(
            $this->members,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
        );
    }
}
