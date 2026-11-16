<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\RequestLoginCode;

use App\Identity\Domain\CodeAttempt;
use App\Identity\Domain\LoginChallenge;
use App\Identity\Infrastructure\HmacLoginCodeHasher;
use App\Identity\UseCase\RequestLoginCode\RequestLoginCode;
use App\Identity\UseCase\RequestLoginCode\RequestLoginCodeHandler;
use App\Identity\UseCase\RequestLoginCode\RequestLoginCodeOutput;
use App\Membership\Domain\Member;
use App\Membership\Infrastructure\RepositoryMemberDirectory;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Notification;
use App\Tests\Identity\Fake\FixedLoginCodeGenerator;
use App\Tests\Identity\Fake\InMemoryLoginChallengeRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\FakeAuthorization;
use App\Tests\Shared\Fake\ImmediateTransaction;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Shared\Fake\RecordingNotifier;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class RequestLoginCodeHandlerTest extends TestCase
{
    private const string NOW = '2026-11-16T10:00:00+00:00';
    private const string KEY = 'a key for the tests only';

    private InMemoryMemberRepository $members;
    private InMemoryLoginChallengeRepository $challenges;
    private RecordingNotifier $notifier;
    private RecordingAuditTrail $auditTrail;
    private Member $jan;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
        $this->challenges = new InMemoryLoginChallengeRepository();
        $this->notifier = new RecordingNotifier();
        $this->auditTrail = new RecordingAuditTrail();
        $this->jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
    }

    #[Test]
    public function it_mails_the_member_a_code_in_two_groups_that_works_with_the_challenge_id_it_answers(): void
    {
        $output = $this->handler()(new RequestLoginCode(' Jan@Example.org '));

        $this->assertEquals(new RequestLoginCodeOutput('0123456789abcdef0123456789abcdef'), $output);
        $this->assertEquals([new Notification($this->jan->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60])], $this->notifier->sent);
        $this->assertSame(CodeAttempt::Accepted, $this->tryCode('0123456789abcdef0123456789abcdef', '482913'));
    }

    #[Test]
    public function it_records_the_request_for_the_member(): void
    {
        $this->handler()(new RequestLoginCode('jan@example.org'));

        $this->assertEquals([new AuditedAction('account.login_code_requested', 'member', $this->jan->id())], $this->auditTrail->recorded);
    }

    /**
     * The answer has the same shape as for a member, so it does not tell whether an address belongs to one; the
     * challenge exists, so a code typed for it fails like a wrong code.
     */
    #[Test]
    public function it_answers_an_unknown_address_with_a_challenge_that_takes_no_code_and_sends_and_records_nothing(): void
    {
        $output = $this->handler()(new RequestLoginCode('onbekend@example.org'));

        $this->assertEquals(new RequestLoginCodeOutput('0123456789abcdef0123456789abcdef'), $output);
        $this->assertSame([[], []], [$this->notifier->sent, $this->auditTrail->recorded]);
        $this->assertSame(CodeAttempt::Wrong, $this->tryCode('0123456789abcdef0123456789abcdef', '482913'));
    }

    #[Test]
    public function it_answers_a_deactivated_member_like_an_unknown_address(): void
    {
        $this->jan->deactivate();

        $output = $this->handler()(new RequestLoginCode('jan@example.org'));

        $this->assertEquals(new RequestLoginCodeOutput('0123456789abcdef0123456789abcdef'), $output);
        $this->assertSame([[], []], [$this->notifier->sent, $this->auditTrail->recorded]);
        $this->assertSame(CodeAttempt::Wrong, $this->tryCode('0123456789abcdef0123456789abcdef', '482913'));
    }

    #[Test]
    public function it_ends_the_open_challenge_of_the_member_when_a_new_one_is_requested(): void
    {
        $handler = $this->handler();
        $handler(new RequestLoginCode('jan@example.org'));

        $handler(new RequestLoginCode('jan@example.org'));

        $this->assertSame(
            [CodeAttempt::Ended, CodeAttempt::Accepted],
            [$this->tryCode('0123456789abcdef0123456789abcdef', '482913'), $this->tryCode('fedcba9876543210fedcba9876543210', '482913')],
        );
    }

    #[Test]
    public function it_opens_no_challenge_when_access_is_denied(): void
    {
        $authorization = FakeAuthorization::refusing();

        $refusal = null;
        try {
            $this->handler($authorization)(new RequestLoginCode('jan@example.org'));
        } catch (AccessDenied $exception) {
            $refusal = $exception;
        }

        $this->assertInstanceOf(AccessDenied::class, $refusal);
        $this->assertEquals([['account.request_login_code', null]], $authorization->asked);
        $this->assertSame([[], [], []], [$this->challenges->all(), $this->notifier->sent, $this->auditTrail->recorded]);
    }

    private function tryCode(string $challengeId, string $code): CodeAttempt
    {
        $challenge = $this->challenges->forUpdate($challengeId);
        $this->assertInstanceOf(LoginChallenge::class, $challenge);

        return $challenge->attempt(new HmacLoginCodeHasher(self::KEY)->hash($challengeId, $code), new DateTimeImmutable(self::NOW));
    }

    private function handler(?FakeAuthorization $authorization = null): RequestLoginCodeHandler
    {
        return new RequestLoginCodeHandler(
            new RepositoryMemberDirectory($this->members),
            $this->challenges,
            new FixedLoginCodeGenerator(),
            new HmacLoginCodeHasher(self::KEY),
            $this->notifier,
            $authorization ?? FakeAuthorization::granting(),
            $this->auditTrail,
            new ImmediateTransaction(),
            new MockClock(self::NOW),
        );
    }
}
