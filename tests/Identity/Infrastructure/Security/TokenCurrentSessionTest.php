<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Domain\Account;
use App\Identity\Infrastructure\Security\MemberUserProvider;
use App\Identity\Infrastructure\Security\TokenCurrentSession;
use App\Membership\Domain\Member;
use App\Membership\Infrastructure\RepositoryMemberDirectory;
use App\Shared\Domain\Channel;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use App\Tests\Identity\Fake\InMemoryAccountRepository;
use App\Tests\Identity\Fake\RecordingRememberMeHandler;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class TokenCurrentSessionTest extends TestCase
{
    private InMemoryMemberRepository $members;
    private InMemoryAccountRepository $accounts;
    private TokenStorage $tokenStorage;
    private Member $jan;

    private RecordingRememberMeHandler $rememberMe;

    #[Override]
    protected function setUp(): void
    {
        $this->members = new InMemoryMemberRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->tokenStorage = new TokenStorage();
        $this->rememberMe = new RecordingRememberMeHandler();
        $this->jan = MemberFixture::stored($this->members, 'jan@example.org', 'Jan Bakker', 'Jan B.');
        $this->accounts->add(Account::open($this->jan->id()));
    }

    #[Test]
    public function it_takes_the_new_password_into_the_session_and_keeps_what_the_session_knew(): void
    {
        $token = $this->sessionOf(new MemberUser($this->jan->id(), $this->jan->roles()));
        $this->newPassword();

        $this->session(new Request())->keepAfterNewPassword($this->jan->id());

        $this->assertEquals(new MemberUser($this->jan->id(), $this->jan->roles(), '1794823200.000000'), $token->getUser());
        $this->assertSame(Channel::Web, $token->getAttribute(TokenExecutionContext::CHANNEL));
        $this->assertSame([], $this->rememberMe->cookiesFor);
    }

    /**
     * The device the member changed it on stays remembered; every other device loses its cookie.
     */
    #[Test]
    public function it_renews_the_stay_logged_in_cookie_the_request_came_with(): void
    {
        $this->sessionOf(new MemberUser($this->jan->id(), $this->jan->roles()));
        $this->newPassword();

        $this->session(new Request(cookies: ['bp_remember' => 'signed']))->keepAfterNewPassword($this->jan->id());

        $this->assertEquals([new MemberUser($this->jan->id(), $this->jan->roles(), '1794823200.000000')], $this->rememberMe->cookiesFor);
    }

    #[Test]
    public function it_leaves_the_session_of_another_member_alone(): void
    {
        $other = new MemberUser(MemberFixture::stored($this->members, 'piet@example.org', 'Piet de Vries', 'Piet V.')->id(), []);
        $token = $this->sessionOf($other);
        $this->newPassword();

        $this->session(new Request(cookies: ['bp_remember' => 'signed']))->keepAfterNewPassword($this->jan->id());

        $this->assertSame([$other, []], [$token->getUser(), $this->rememberMe->cookiesFor]);
    }

    private function sessionOf(MemberUser $user): UsernamePasswordToken
    {
        $token = new UsernamePasswordToken($user, 'browser', $user->getRoles());
        $token->setAttribute(TokenExecutionContext::CHANNEL, Channel::Web);
        $this->tokenStorage->setToken($token);

        return $token;
    }

    private function newPassword(): void
    {
        $this->accounts->ofMember($this->jan->id())?->changePassword('a new hash', new DateTimeImmutable('2026-11-16T10:00:00+00:00'));
    }

    private function session(Request $request): TokenCurrentSession
    {
        $requests = new RequestStack([$request]);

        return new TokenCurrentSession(
            $this->tokenStorage,
            new MemberUserProvider(new RepositoryMemberDirectory($this->members), $this->accounts),
            $requests,
            $this->rememberMe,
        );
    }
}
