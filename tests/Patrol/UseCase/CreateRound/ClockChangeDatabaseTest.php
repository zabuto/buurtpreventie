<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\CreateRound;

use App\Kernel;
use App\Membership\Domain\Member;
use App\Membership\Domain\MemberDetails;
use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\Channel;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use App\Tests\Database\TestDatabase;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\InMemoryKernel;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

/**
 * Review focus 2 through the API once along the whole path: the four evenings around the clock changes, each written
 * with another offset, are stored by Doctrine and read back at 22:00 local time. The app clock stands still at the
 * moment of the in-memory kernel, so the evenings stay ahead.
 */
#[Group('database')]
#[Group('functional')]
final class ClockChangeDatabaseTest extends ApiTestCase
{
    /**
     * Null when the test skipped before it stopped the clock: tearDown() runs after a skip as well.
     */
    private ?ClockInterface $previousClock = null;

    #[Override]
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        TestDatabase::require();
        $this->previousClock = Clock::get();
        Clock::set(new MockClock(InMemoryKernel::NOW));
    }

    #[Override]
    protected function tearDown(): void
    {
        if (null !== $this->previousClock) {
            Clock::set($this->previousClock);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_shows_every_round_at_ten_in_the_evening_after_a_trip_through_the_database(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::loggedInWalker($client);
        $notations = [0, 1, 2, 0];
        $expected = [];
        foreach (\array_values(ClockChangeEvenings::evenings()) as $index => $evening) {
            $client->jsonRequest('POST', '/api/v1/rounds', ['startsAt' => $evening[$notations[$index]]]);
            self::assertResponseStatusCodeSame(201);
            $id = self::json($client)['id'] ?? null;
            self::assertIsString($id);
            $expected[$id] = $evening[0];
        }

        self::service(EntityManagerInterface::class)->clear();

        $client->request('GET', '/api/v1/me/rounds', ['view' => 'upcoming']);
        self::assertSame(self::asEvenings($expected), self::listed($client));
        foreach ($expected as $id => $startsAt) {
            $client->request('GET', '/api/v1/rounds/' . $id);
            /** @var array{id: string, startsAt: string, daypart: string} $round */
            $round = self::json($client);
            self::assertSame([$id, $startsAt, 'evening'], [$round['id'], $round['startsAt'], $round['daypart']]);

            $client->request('GET', '/api/v1/rounds', ClockChangeEvenings::dayAround($startsAt));
            self::assertContains([$id, $startsAt, 'evening'], self::listed($client));
        }
    }

    private static function loggedInWalker(KernelBrowser $client): void
    {
        $suffix = \bin2hex(\random_bytes(6));
        $member = Member::register(new MemberDetails(
            EmailAddress::fromString('loper' . $suffix . '@example.org'),
            'Loper ' . $suffix,
            'Loper ' . $suffix,
        ));
        $member->changeRoles([Role::Walker]);
        self::service(MemberRepository::class)->add($member);
        self::service(EntityManagerInterface::class)->flush();
        $client->loginUser(new MemberUser($member->id(), $member->roles()), 'browser', [TokenExecutionContext::CHANNEL => Channel::Web]);
    }

    /**
     * @param array<string, string> $expected
     *
     * @return list<array{string, string, string}>
     */
    private static function asEvenings(array $expected): array
    {
        return \array_map(static fn(string $id, string $startsAt): array => [$id, $startsAt, 'evening'], \array_keys($expected), \array_values($expected));
    }

    /**
     * @return list<array{string, string, string}>
     */
    private static function listed(KernelBrowser $client): array
    {
        self::assertResponseIsSuccessful();
        /** @var array{rounds: list<array{id: string, startsAt: string, daypart: string}>} $answer */
        $answer = self::json($client);

        return \array_map(static fn(array $round): array => [$round['id'], $round['startsAt'], $round['daypart']], $answer['rounds']);
    }
}
