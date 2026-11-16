<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\RequestLoginCode;

use App\Shared\Domain\Notification;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Shared\Fake\RecordingNotifier;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[Group('functional')]
final class RequestLoginCodeControllerTest extends ApiTestCase
{
    #[Test]
    public function it_accepts_the_request_of_a_member_with_the_challenge_id_and_mails_the_code(): void
    {
        $client = self::client();
        $jan = MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'jan@example.org', 'Jan Bakker', 'Jan B.');

        self::requestCode($client, 'jan@example.org');

        self::assertResponseStatusCodeSame(202);
        self::assertSame(['challengeId' => '0123456789abcdef0123456789abcdef'], self::json($client));
        self::assertEquals(
            [new Notification($jan->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60])],
            self::service(RecordingNotifier::class)->sent,
        );
    }

    #[Test]
    public function it_answers_an_unknown_address_exactly_like_a_known_one(): void
    {
        $client = self::client();

        self::requestCode($client, 'onbekend@example.org');

        self::assertResponseStatusCodeSame(202);
        self::assertSame(['challengeId' => '0123456789abcdef0123456789abcdef'], self::json($client));
        self::assertSame([], self::service(RecordingNotifier::class)->sent);
    }

    #[Test]
    public function it_refuses_a_request_without_an_address_as_invalid_input(): void
    {
        $client = self::client();

        $client->jsonRequest('POST', '/api/v1/auth/login-code', ['email' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('urn:buurtpreventie:problem:invalid_input', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_takes_three_requests_for_one_address_within_an_hour_and_refuses_the_fourth(): void
    {
        $client = self::client();
        for ($request = 1; $request <= 3; ++$request) {
            self::requestCode($client, 'jan@example.org', ip: \sprintf('192.0.2.%d', $request));
            self::assertResponseStatusCodeSame(202, \sprintf('request %d', $request));
        }

        self::requestCode($client, ' Jan@Example.org', ip: '192.0.2.4');

        self::assertResponseStatusCodeSame(429);
        self::assertSame('urn:buurtpreventie:problem:too_many_requests', self::json($client)['type'] ?? null);
        $retryAfter = (int) $client->getResponse()->headers->get('Retry-After');
        // Rounded up to whole seconds, so the hour can show as 3601.
        self::assertTrue($retryAfter > 0 && $retryAfter <= 3601, \sprintf('Retry-After %d is not seconds within the hour', $retryAfter));
    }

    #[Test]
    public function it_takes_ten_requests_from_one_ip_address_within_an_hour_and_refuses_the_eleventh(): void
    {
        $client = self::client();
        for ($request = 1; $request <= 10; ++$request) {
            self::requestCode($client, \sprintf('lid%d@example.org', $request));
            self::assertResponseStatusCodeSame(202, \sprintf('request %d', $request));
        }

        self::requestCode($client, 'jan@example.org');

        self::assertResponseStatusCodeSame(429);
        self::requestCode($client, 'jan@example.org', ip: '192.0.2.99');
        self::assertResponseStatusCodeSame(202);
    }

    /**
     * Every request of a test shares one kernel, so the counted requests carry over.
     */
    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();

        return $client;
    }

    private static function requestCode(KernelBrowser $client, string $email, string $ip = '127.0.0.1'): void
    {
        $client->jsonRequest('POST', '/api/v1/auth/login-code', ['email' => $email], ['REMOTE_ADDR' => $ip]);
    }
}
