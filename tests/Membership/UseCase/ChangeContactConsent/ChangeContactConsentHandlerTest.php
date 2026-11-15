<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\ChangeContactConsent;

use App\Membership\Domain\Member;
use App\Membership\UseCase\ChangeContactConsent\ChangeContactConsent;
use App\Membership\UseCase\ChangeContactConsent\ChangeContactConsentHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChangeContactConsentHandlerTest extends TestCase
{
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
    public function it_records_the_consent_to_share_contact_details(): void
    {
        $this->handler()(new ChangeContactConsent($this->jan->id()->toRfc4122(), consent: true));

        $this->assertTrue($this->jan->consentsToContactShare());
        $this->assertEquals(
            [new AuditedAction('member.consent_changed', 'member', $this->jan->id(), [
                'consentContactShare' => ['old' => false, 'new' => true],
            ])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_asks_whether_the_actor_may_change_this_members_consent(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new ChangeContactConsent($this->jan->id()->toRfc4122(), consent: true));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['member.change_consent', $this->jan]], $authorization->asked);
        $this->assertFalse($this->jan->consentsToContactShare());
    }

    private function handler(?FakeAuthorization $authorization = null): ChangeContactConsentHandler
    {
        return new ChangeContactConsentHandler(
            $this->members,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
        );
    }
}
