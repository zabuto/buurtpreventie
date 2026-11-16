<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Domain\Invitation;
use App\Membership\Domain\Member;
use App\Shared\Domain\AuditedAction;
use App\Tests\Identity\Fake\InMemoryInvitationRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\MockClock;

#[Group('functional')]
final class InvitationAuthenticatorTest extends ApiTestCase
{
    // @mago-expect lint:no-literal-password - a made-up secret the test logs in with
    private const string SECRET = 'bpi_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private const array INVITATION_ENDED = [
        'type' => 'urn:buurtpreventie:problem:invitation_ended',
        'title' => 'This invitation no longer works. Ask for a login code, or ask an admin for a new invitation.',
        'status' => 401,
    ];

    #[Test]
    public function it_logs_the_new_member_in_on_the_web_channel_as_a_recent_login(): void
    {
        $client = self::client();
        $jan = self::invited();

        self::accept($client, self::SECRET);

        self::assertResponseStatusCodeSame(204);
        $client->request('GET', '/api/v1/_probe-identity');
        self::assertSame(['memberId' => $jan->id()->toRfc4122(), 'roles' => ['walker'], 'channel' => 'web', 'recent' => true], self::json($client));
    }

    #[Test]
    public function it_records_the_login_with_the_invitation_as_its_method(): void
    {
        $client = self::client();
        $jan = self::invited();

        self::accept($client, self::SECRET, ip: '192.0.2.57');

        self::assertEquals(
            [new AuditedAction('account.logged_in', 'member', $jan->id(), [
                'ip' => ['old' => null, 'new' => '192.0.2.0'],
                'method' => ['old' => null, 'new' => 'invitation'],
            ])],
            self::service(RecordingAuditTrail::class)->recorded,
        );
    }

    #[Test]
    public function it_takes_an_invitation_once(): void
    {
        $client = self::client();
        self::invited();
        self::accept($client, self::SECRET);
        $client->request('POST', '/api/v1/auth/logout');

        self::accept($client, self::SECRET);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(self::INVITATION_ENDED, self::json($client));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function momentsAfterTheInvitation(): iterable
    {
        yield 'a second before 48 hours' => ['+47 hours 59 minutes 59 seconds', 204];
        yield 'at 48 hours' => ['+48 hours', 401];
    }

    #[DataProvider('momentsAfterTheInvitation')]
    #[Test]
    public function it_takes_an_invitation_for_48_hours(string $later, int $status): void
    {
        $client = self::client();
        self::invited();

        self::service(MockClock::class)->modify($later);
        self::accept($client, self::SECRET);

        self::assertResponseStatusCodeSame($status);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownSecrets(): iterable
    {
        yield 'another secret' => ['bpi_fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210'];
        yield 'an API token' => ['bpt_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'];
        yield 'no secret' => [''];
    }

    #[DataProvider('unknownSecrets')]
    #[Test]
    public function it_refuses_an_unknown_secret_like_an_invitation_that_ended(#[SensitiveParameter] string $secret): void
    {
        $client = self::client();
        self::invited();

        self::accept($client, $secret);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(self::INVITATION_ENDED, self::json($client));
        self::assertSame([], self::service(RecordingAuditTrail::class)->recorded);
    }

    #[Test]
    public function it_refuses_the_invitation_of_a_member_who_was_deactivated_since_and_leaves_it_unused(): void
    {
        $client = self::client();
        $jan = self::invited();

        $jan->deactivate();
        self::accept($client, self::SECRET);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(self::INVITATION_ENDED, self::json($client));
        self::assertTrue(self::service(InMemoryInvitationRepository::class)->all()[0]->isOpenAt(self::service(MockClock::class)->now()));
    }

    /**
     * Every request of a test shares one kernel, so the session, the fakes and the clock carry over.
     */
    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();

        return $client;
    }

    private static function accept(KernelBrowser $client, #[SensitiveParameter] string $secret, string $ip = '127.0.0.1'): void
    {
        $client->jsonRequest('POST', '/api/v1/auth/invitation', ['secret' => $secret], ['REMOTE_ADDR' => $ip]);
    }

    private static function invited(): Member
    {
        $member = MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'jan@example.org', 'Jan Bakker', 'Jan B.');
        self::service(InMemoryInvitationRepository::class)->add(Invitation::issue($member->id(), self::SECRET, self::service(MockClock::class)->now()));

        return $member;
    }
}
