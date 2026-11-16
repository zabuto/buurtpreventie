<?php

declare(strict_types=1);

namespace App\Tests\Audit\UseCase\ListAuditEntries;

use App\Audit\Domain\AuditEntry;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

/**
 * GET /admin/audit: the whole log for the admin, as JSON a page at a time or as a CSV file.
 */
#[Group('functional')]
final class AuditLogEndpointsTest extends ApiTestCase
{
    #[Test]
    public function it_lists_the_entries_of_one_member_for_the_admin(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);
        $jan = Uuid::v7();
        self::stored($jan, 'round.created');
        self::stored(Uuid::v7(), 'round.created');

        $client->request('GET', '/api/v1/admin/audit', ['member' => $jan->toRfc4122()]);

        self::assertResponseIsSuccessful();
        /** @var array{entries: list<array{actorId: string|null}>, nextPage: int|null} $answer */
        $answer = self::json($client);
        self::assertSame([[$jan->toRfc4122()], null], [\array_column($answer['entries'], 'actorId'), $answer['nextPage']]);
    }

    #[Test]
    public function it_hands_over_a_csv_file(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);
        self::stored(Uuid::v7(), 'round.created', '=1+1');

        $client->request('GET', '/api/v1/admin/audit', ['format' => 'csv']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=utf-8');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename="audit.csv"');
        $lines = \explode("\n", (string) $client->getResponse()->getContent());
        self::assertSame('occurredAt,action,actor,actorId,actorName,subjectType,subjectId,channel,changes', $lines[0]);
        self::assertStringContainsString(",'=1+1,", $lines[1] ?? '');
    }

    #[Test]
    public function it_refuses_the_log_to_a_coordinator(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Coordinator);

        $client->request('GET', '/api/v1/admin/audit');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_refuses_a_period_in_another_format(): void
    {
        $client = self::client();
        self::actingAs($client, Role::Admin);

        $client->request('GET', '/api/v1/admin/audit', ['from' => '14-11-2026']);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * One kernel for every request of the test, so the entries stored before the first request stay.
     */
    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();

        return $client;
    }

    private static function stored(Uuid $actor, string $action, string $actorName = 'Jan B.'): void
    {
        self::service(RecordingAuditEntryRepository::class)->add(AuditEntry::of(
            new AuditedAction($action, 'round', Uuid::v7()),
            $actor,
            $actorName,
            Channel::Web,
            new DateTimeImmutable('2026-11-15T12:00:00+00:00'),
        ));
    }
}
