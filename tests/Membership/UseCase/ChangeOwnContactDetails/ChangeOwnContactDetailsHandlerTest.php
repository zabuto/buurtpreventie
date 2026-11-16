<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\ChangeOwnContactDetails;

use App\Membership\Domain\InvalidPhoneNumber;
use App\Membership\Domain\Member;
use App\Membership\UseCase\ChangeContactConsent\ChangeContactConsentHandler;
use App\Membership\UseCase\ChangeOpenRoundNotifications\ChangeOpenRoundNotificationsHandler;
use App\Membership\UseCase\ChangeOwnContactDetails\ChangeOwnContactDetails;
use App\Membership\UseCase\ChangeOwnContactDetails\ChangeOwnContactDetailsHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AuditedAction;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\FixedExecutionContext;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChangeOwnContactDetailsHandlerTest extends TestCase
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
    public function it_changes_the_phone_number_and_address_of_the_member_acting(): void
    {
        $this->handler()(new ChangeOwnContactDetails(phone: '020 123 4567', address: 'Kerkstraat 2'));

        $this->assertSame(['020 123 4567', 'Kerkstraat 2'], [$this->jan->phone()?->value, $this->jan->address()]);
    }

    #[Test]
    public function it_leaves_out_what_the_input_leaves_out(): void
    {
        $this->handler()(new ChangeOwnContactDetails(address: 'Kerkstraat 2'));

        $this->assertSame(['06 1234 5678', 'Kerkstraat 2', false], [
            $this->jan->phone()?->value,
            $this->jan->address(),
            $this->jan->consentsToContactShare(),
        ]);
    }

    #[Test]
    public function it_removes_a_phone_number_and_address_given_as_null(): void
    {
        $this->handler()(new ChangeOwnContactDetails(phone: null, address: null));

        $this->assertSame([null, null], [$this->jan->phone(), $this->jan->address()]);
    }

    /**
     * Like a change by the admin, the audit log names the fields only, so it never outlives an erasure with them.
     */
    #[Test]
    public function it_records_which_contact_details_changed_without_their_values(): void
    {
        $this->handler()(new ChangeOwnContactDetails(phone: '020 123 4567', address: 'Dorpsstraat 1'));

        $this->assertEquals(
            [new AuditedAction('member.contact_details_changed', 'member', $this->jan->id(), [
                'phone' => ['old' => null, 'new' => null],
            ])],
            $this->auditTrail->recorded,
        );
    }

    #[Test]
    public function it_records_consent_and_notifications_as_their_own_actions(): void
    {
        $this->handler()(new ChangeOwnContactDetails(consentContactShare: true, notifyOpenRounds: true));

        $this->assertEquals(
            [
                new AuditedAction('member.consent_changed', 'member', $this->jan->id(), ['consentContactShare' => ['old' => false, 'new' => true]]),
                new AuditedAction('member.open_round_notifications_changed', 'member', $this->jan->id(), ['notifyOpenRounds' => [
                    'old' => false,
                    'new' => true,
                ]]),
            ],
            $this->auditTrail->recorded,
        );
    }

    /**
     * The frontend sends the whole form; a value as it was is no change.
     */
    #[Test]
    public function it_records_nothing_when_every_value_stays_as_it_was(): void
    {
        $this->handler()(new ChangeOwnContactDetails('06 1234 5678', 'Dorpsstraat 1', false, false));

        $this->assertSame([], $this->auditTrail->recorded);
    }

    #[Test]
    public function it_changes_the_member_it_names(): void
    {
        $piet = MemberFixture::stored($this->members, 'piet@example.org', 'Piet de Vries', 'Piet V.');

        $this->handler()(new ChangeOwnContactDetails(address: 'Kerkstraat 2', memberId: $piet->id()->toRfc4122()));

        $this->assertSame(['Kerkstraat 2', 'Dorpsstraat 1'], [$piet->address(), $this->jan->address()]);
    }

    #[Test]
    public function it_changes_nothing_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new ChangeOwnContactDetails(address: 'Kerkstraat 2', consentContactShare: true));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertSame([['member.change_contact_details', $this->jan]], $authorization->asked);
        $this->assertSame(['Dorpsstraat 1', false, []], [$this->jan->address(), $this->jan->consentsToContactShare(), $this->auditTrail->recorded]);
    }

    #[Test]
    public function it_refuses_an_invalid_phone_number(): void
    {
        $this->expectException(InvalidPhoneNumber::class);

        $this->handler()(new ChangeOwnContactDetails(phone: 'bel me'));
    }

    #[Test]
    public function it_refuses_when_nobody_acts_and_no_member_is_given(): void
    {
        $this->expectException(AccessDenied::class);

        $this->handler(withActor: false)(new ChangeOwnContactDetails(address: 'Kerkstraat 2'));
    }

    private function handler(?FakeAuthorization $authorization = null, bool $withActor = true): ChangeOwnContactDetailsHandler
    {
        $authorization ??= FakeAuthorization::granting();
        $transaction = new ImmediateTransaction();

        return new ChangeOwnContactDetailsHandler(
            $this->members,
            new ChangeContactConsentHandler($this->members, $authorization, $this->auditTrail, $transaction),
            new ChangeOpenRoundNotificationsHandler($this->members, $authorization, $this->auditTrail, $transaction),
            $authorization,
            new FixedExecutionContext($withActor ? new Actor($this->jan->id(), []) : null),
            $this->auditTrail,
            $transaction,
        );
    }
}
