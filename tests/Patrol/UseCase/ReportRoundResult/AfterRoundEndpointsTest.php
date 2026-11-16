<?php

declare(strict_types=1);

namespace App\Tests\Patrol\UseCase\ReportRoundResult;

use App\Audit\Domain\AuditEntry;
use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundComment;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Patrol\Fake\InMemoryRoundCommentRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

/**
 * After the round: reports and comments, the reports for analysis and the history of a round. The clock of the
 * kernel stands at 16 November 2026, 11:00 in Europe/Amsterdam.
 */
#[Group('functional')]
final class AfterRoundEndpointsTest extends ApiTestCase
{
    private const string WALKED = '2026-11-14T21:00:00+00:00';
    private const string CREATED_AT = '2026-11-10T09:00:00+00:00';
    private const string REPORTED_AT = '2026-11-15T09:00:00+00:00';

    #[Test]
    public function it_takes_the_report_of_a_walker_of_the_round(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::walkedRound($member->id());
        $outcome = self::storedOutcome();

        $client->jsonRequest('POST', self::path($round, 'reports'), ['outcomeId' => $outcome->id()->toRfc4122(), 'note' => 'Rustig']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(self::json($client)['id'] ?? null, $round->report()?->id()->toRfc4122());
    }

    #[Test]
    public function it_refuses_a_second_report(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::walkedRound($member->id());
        $outcome = self::storedOutcome();
        $round->submitReport($member->id(), $outcome, null, new DateTimeImmutable(self::REPORTED_AT));

        $client->jsonRequest('POST', self::path($round, 'reports'), ['outcomeId' => $outcome->id()->toRfc4122()]);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('urn:buurtpreventie:problem:round_already_reported', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_deletes_an_own_report(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::walkedRound($member->id());
        $report = $round->submitReport($member->id(), self::storedOutcome(), null, new DateTimeImmutable(self::REPORTED_AT));

        $client->request('DELETE', '/api/v1/reports/' . $report->id()->toRfc4122());

        self::assertResponseStatusCodeSame(204);
        self::assertNull($round->report());
    }

    #[Test]
    public function it_adds_and_deletes_a_comment(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::walkedRound($member->id());

        $client->jsonRequest('POST', self::path($round, 'comments'), ['text' => 'Mooie avond.']);
        self::assertResponseStatusCodeSame(201);
        $id = self::json($client)['id'] ?? null;
        self::assertIsString($id);
        $client->request('DELETE', '/api/v1/comments/' . $id);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], self::service(InMemoryRoundCommentRepository::class)->forRound($round));
    }

    #[Test]
    public function it_refuses_a_comment_by_a_walker_of_another_round(): void
    {
        $client = self::client();
        self::actingAs($client);
        $round = self::walkedRound(Uuid::v7());

        $client->jsonRequest('POST', self::path($round, 'comments'), ['text' => 'Mooie avond.']);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_shows_the_note_and_comments_to_a_walker_of_the_round(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::reportedRound($member->id());

        $client->request('GET', '/api/v1/rounds/' . $round->id()->toRfc4122());

        self::assertSame(['Raam open', ['Politie gebeld.']], self::noteAndComments($client));
    }

    #[Test]
    public function it_shows_the_note_and_comments_to_an_analyst(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Analyst);
        $round = self::reportedRound(Uuid::v7());

        $client->request('GET', '/api/v1/rounds/' . $round->id()->toRfc4122());

        self::assertSame(['Raam open', ['Politie gebeld.']], self::noteAndComments($client));
    }

    #[Test]
    public function it_hides_the_note_and_comments_from_a_walker_of_another_round(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Walker, Role::Admin);
        $round = self::reportedRound(Uuid::v7());

        $client->request('GET', '/api/v1/rounds/' . $round->id()->toRfc4122());

        self::assertSame([null, []], self::noteAndComments($client));
    }

    #[Test]
    public function it_lists_the_reports_with_their_notes_for_an_analyst(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Analyst);
        $round = self::reportedRound(Uuid::v7());

        $client->request('GET', '/api/v1/reports', ['from' => '2026-11-01T00:00:00Z', 'to' => '2026-12-01T00:00:00Z']);

        self::assertResponseIsSuccessful();
        /** @var array{reports: list<array{roundId: string, note: string|null}>} $answer */
        $answer = self::json($client);
        self::assertSame([[$round->id()->toRfc4122(), 'Raam open']], \array_map(
            static fn(array $report): array => [$report['roundId'], $report['note']],
            $answer['reports'],
        ));
    }

    #[Test]
    public function it_refuses_the_reports_to_a_walker(): void
    {
        $client = self::client();
        self::actingAs($client);

        $client->request('GET', '/api/v1/reports', ['from' => '2026-11-01T00:00:00Z', 'to' => '2026-12-01T00:00:00Z']);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_refuses_reports_over_more_than_93_days(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Coordinator);

        $client->request('GET', '/api/v1/reports', ['from' => '2026-08-01T00:00:00Z', 'to' => '2026-12-01T00:00:00Z']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('urn:buurtpreventie:problem:invalid_period', self::json($client)['type'] ?? null);
    }

    #[Test]
    public function it_shows_an_erased_member_in_the_history_as_a_removed_member(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Coordinator);
        $round = Uuid::v7();
        $entry = AuditEntry::of(
            new AuditedAction('participation.withdrawn', 'round', $round),
            Uuid::v7(),
            'Piet V.',
            Channel::Web,
            new DateTimeImmutable(self::REPORTED_AT),
        );
        $entry->eraseActorName();
        self::service(RecordingAuditEntryRepository::class)->add($entry);

        $client->request('GET', \sprintf('/api/v1/rounds/%s/history', $round->toRfc4122()));

        self::assertResponseIsSuccessful();
        /** @var array{entries: list<array{action: string, actor: string, actorName: string|null}>} $history */
        $history = self::json($client);
        self::assertSame(
            [['participation.withdrawn', 'removed_member', null]],
            \array_map(static fn(array $line): array => [$line['action'], $line['actor'], $line['actorName']], $history['entries']),
        );
    }

    #[Test]
    public function it_refuses_the_history_to_a_walker(): void
    {
        $client = self::client();
        $member = self::actingAs($client);
        $round = self::walkedRound($member->id());

        $client->request('GET', self::path($round, 'history'));

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * One kernel for every request of the test, so the data stored before the first request stays.
     */
    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();

        return $client;
    }

    private static function walkedRound(Uuid $firstWalker): Round
    {
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable(self::WALKED)),
            $firstWalker,
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable(self::CREATED_AT),
        );
        $round->signUp(Uuid::v7(), new DateTimeImmutable(self::CREATED_AT));
        self::service(InMemoryRoundRepository::class)->add($round);

        return $round;
    }

    private static function reportedRound(Uuid $firstWalker): Round
    {
        $round = self::walkedRound($firstWalker);
        $round->submitReport($firstWalker, self::storedOutcome(), 'Raam open', new DateTimeImmutable(self::REPORTED_AT));
        self::service(InMemoryRoundCommentRepository::class)->add(
            RoundComment::post($round, $firstWalker, 'Politie gebeld.', new DateTimeImmutable(self::REPORTED_AT)),
        );

        return $round;
    }

    private static function storedOutcome(): Outcome
    {
        $outcome = Outcome::create('Incident', OutcomeKind::Incident);
        self::service(InMemoryOutcomeRepository::class)->add($outcome);

        return $outcome;
    }

    /**
     * @return array{string|null, list<string>}
     */
    private static function noteAndComments(KernelBrowser $client): array
    {
        self::assertResponseIsSuccessful();
        /** @var array{report: array{note: string|null}|null, comments: list<array{text: string}>} $round */
        $round = self::json($client);

        return [$round['report']['note'] ?? null, \array_column($round['comments'], 'text')];
    }

    private static function path(Round $round, string $part): string
    {
        return \sprintf('/api/v1/rounds/%s/%s', $round->id()->toRfc4122(), $part);
    }
}
