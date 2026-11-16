<?php

declare(strict_types=1);

namespace App\Tests\Audit\UseCase\ListOwnActivity;

use App\Audit\Domain\AuditEntry;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class ListOwnActivityControllerTest extends ApiTestCase
{
    #[Test]
    public function it_shows_the_member_logged_in_their_own_activity(): void
    {
        $client = self::createClient();
        $member = self::actingAs($client);
        self::service(RecordingAuditEntryRepository::class)->add(AuditEntry::of(
            new AuditedAction('member.consent_changed', 'member', $member->id()),
            $member->id(),
            'Ingelogd L.',
            Channel::Web,
            new DateTimeImmutable('2026-11-15T12:00:00+00:00'),
        ));

        $client->request('GET', '/api/v1/me/activity');

        self::assertResponseIsSuccessful();
        /** @var array{entries: list<array{action: string}>} $activity */
        $activity = self::json($client);
        self::assertSame(['member.consent_changed'], \array_column($activity['entries'], 'action'));
    }

    #[Test]
    public function it_refuses_a_page_below_one(): void
    {
        $client = self::createClient();
        self::actingAs($client);

        $client->request('GET', '/api/v1/me/activity?page=0');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_refuses_a_visitor_who_is_not_logged_in(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/me/activity');

        self::assertResponseStatusCodeSame(401);
    }
}
