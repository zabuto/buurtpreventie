<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Membership\Domain\Member;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * An endpoint test on the in-memory kernel: no database, real security and voters.
 */
abstract class ApiTestCase extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return InMemoryKernel::class;
    }

    /**
     * A client that presents itself as the frontend on the same origin, as a browser does with Sec-Fetch-Site, so a
     * changing request passes the CSRF check unless a test says otherwise; a null server value leaves that header out.
     *
     * @param array<array-key, mixed> $options
     * @param array<array-key, mixed> $server
     */
    #[Override]
    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        return parent::createClient(
            $options,
            \array_filter(['HTTP_SEC_FETCH_SITE' => 'same-origin', ...$server], static fn(mixed $value): bool => null !== $value),
        );
    }

    /**
     * Logs the client in through a session on the browser firewall as a new active member with these roles. The
     * member lives in the fake repository of the running kernel, so the session holds for the first request only,
     * unless the test disables the reboot of the client.
     */
    protected static function actingAs(KernelBrowser $client, Role ...$roles): Member
    {
        $member = MemberFixture::stored(
            self::service(InMemoryMemberRepository::class),
            'ingelogd@example.org',
            'Ingelogd Lid',
            'Ingelogd L.',
            [] === $roles ? [Role::Walker] : \array_values($roles),
        );
        $client->loginUser(new MemberUser($member->id(), $member->roles()), 'browser', [TokenExecutionContext::CHANNEL => Channel::Web]);

        return $member;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected static function json(KernelBrowser $client): array
    {
        $body = \json_decode((string) $client->getResponse()->getContent(), associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }

    /**
     * A fake or other service of the kernel the client runs on, to arrange data before the request or check it after.
     *
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
