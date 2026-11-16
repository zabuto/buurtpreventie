<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\SignUpForRound;

use App\Patrol\Domain\Round\Round;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Role;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Patrol\Domain\Round\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Shared\Fake\RecordingAuditTrail;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

/**
 * PUT and DELETE /rounds/{id}/walkers/me and /walkers/{memberId}: signing up and withdrawing, oneself or on behalf.
 */
#[Group('functional')]
final class ParticipationEndpointsTest extends ApiTestCase
{
    #[Test]
    public function it_signs_up_the_member_logged_in(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::storedRound(walkers: 1);

        $client->request('PUT', self::walkersPath($round, 'me'));

        self::assertResponseStatusCodeSame(204);
        self::assertTrue($round->isSignedUp($member->id()));
    }

    /**
     * A double click or a retry after a lost answer must not fail or sign up twice.
     */
    #[Test]
    public function it_answers_a_second_sign_up_the_same_and_records_one(): void
    {
        $client = self::client();
        self::actingAs($client);
        $round = self::storedRound(walkers: 1);
        $client->request('PUT', self::walkersPath($round, 'me'));

        $client->request('PUT', self::walkersPath($round, 'me'));

        self::assertResponseStatusCodeSame(204);
        self::assertSame(2, $round->walkerCount());
        self::assertSame(['participation.signed_up'], self::recordedActions());
    }

    #[Test]
    public function it_answers_a_second_withdrawal_the_same(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::storedRound(walkers: 1);
        $round->signUp($member->id(), new DateTimeImmutable(RoundFixture::CREATED_AT));
        $client->request('DELETE', self::walkersPath($round, 'me'));

        $client->request('DELETE', self::walkersPath($round, 'me'));

        self::assertResponseStatusCodeSame(204);
        self::assertFalse($round->isSignedUp($member->id()));
        self::assertSame(1, $round->walkerCount());
    }

    #[Test]
    public function it_refuses_a_sign_up_for_a_full_round(): void
    {
        $client = self::client();
        self::actingAs($client);
        $round = self::storedRound(walkers: 2, maximum: 2);

        $client->request('PUT', self::walkersPath($round, 'me'));

        self::assertResponseStatusCodeSame(409);
        self::assertSame('urn:buurtpreventie:problem:round_is_full', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_lets_a_coordinator_sign_up_another_member(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Coordinator);
        $piet = self::otherMember();
        $round = self::storedRound(walkers: 1);

        $client->request('PUT', self::walkersPath($round, $piet->toRfc4122()));

        self::assertResponseStatusCodeSame(204);
        self::assertNotNull($round->participationOf($piet)?->onBehalfOf());
    }

    #[Test]
    public function it_refuses_a_walker_who_signs_up_another_member(): void
    {
        $client = self::client();
        self::actingAs($client);
        $piet = self::otherMember();
        $round = self::storedRound(walkers: 1);

        $client->request('PUT', self::walkersPath($round, $piet->toRfc4122()));

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($round->isSignedUp($piet));
    }

    #[Test]
    public function it_lets_a_coordinator_withdraw_another_member(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Coordinator);
        $piet = self::otherMember();
        $round = self::storedRound(walkers: 2);
        $round->signUp($piet, new DateTimeImmutable(RoundFixture::CREATED_AT));

        $client->request('DELETE', self::walkersPath($round, $piet->toRfc4122()));

        self::assertResponseStatusCodeSame(204);
        self::assertFalse($round->isSignedUp($piet));
        self::assertSame(['participation.walker_removed'], self::recordedActions());
    }

    #[Test]
    public function it_refuses_a_walker_who_withdraws_another_member(): void
    {
        $client = self::client();
        self::actingAs($client);
        $round = self::storedRound(walkers: 2);
        $walkerId = $round->walkerIds()[0];

        $client->request('DELETE', self::walkersPath($round, $walkerId->toRfc4122()));

        self::assertResponseStatusCodeSame(403);
        self::assertTrue($round->isSignedUp($walkerId));
    }

    #[Test]
    public function it_answers_a_round_that_does_not_exist_with_not_found(): void
    {
        $client = self::client();
        self::actingAs($client);

        $client->request('PUT', '/api/v1/rounds/0193a3c0-0000-7000-8000-000000000001/walkers/me');

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function it_refuses_a_visitor_who_is_not_logged_in(): void
    {
        $client = self::client();
        $round = self::storedRound(walkers: 1);

        $client->request('PUT', self::walkersPath($round, 'me'));

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * One kernel for every request of the test, so the round and the member stored before the first request stay.
     */
    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();

        return $client;
    }

    private static function storedRound(int $walkers, int $maximum = 7): Round
    {
        $round = RoundFixture::withWalkers(Uuid::v7(), $walkers, $maximum);
        self::service(InMemoryRoundRepository::class)->add($round);

        return $round;
    }

    private static function otherMember(): Uuid
    {
        return MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'piet@example.org', 'Piet de Vries', 'Piet V.')->id();
    }

    /**
     * @return list<string>
     */
    private static function recordedActions(): array
    {
        return \array_map(static fn(AuditedAction $action): string => $action->action, self::service(RecordingAuditTrail::class)->recorded);
    }

    private static function walkersPath(Round $round, string $walker): string
    {
        return \sprintf('/api/v1/rounds/%s/walkers/%s', $round->id()->toRfc4122(), $walker);
    }
}
