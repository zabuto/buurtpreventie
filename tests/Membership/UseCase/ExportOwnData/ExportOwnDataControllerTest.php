<?php

declare(strict_types=1);

namespace App\Tests\Membership\UseCase\ExportOwnData;

use App\Audit\Domain\AuditEntry;
use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Membership\Domain\Member;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundComment;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Shared\Domain\PhoneNumber;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Patrol\Fake\InMemoryRoundCommentRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class ExportOwnDataControllerTest extends ApiTestCase
{
    // @mago-expect lint:no-literal-password - a made-up secret of a token the export must not show
    private const string SECRET = 'bpt_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    #[Test]
    public function it_hands_the_member_their_profile_rounds_comments_tokens_and_activity_as_a_file(): void
    {
        $client = self::createClient();
        $jan = self::actingAs($client);
        self::sharedRound($jan, self::piet());

        $client->request('GET', '/api/v1/me/export');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=buurtpreventie-export.json');
        /** @var array{profile: array{email: string}, patrol: array{rounds: list<mixed>, comments: list<mixed>}, tokens: array{tokens: list<array{name: string}>}, activity: list<array{action: string}>} $export */
        $export = self::json($client);
        self::assertSame(
            ['ingelogd@example.org', 1, 1, ['Script'], ['member.changed']],
            [
                $export['profile']['email'],
                \count($export['patrol']['rounds']),
                \count($export['patrol']['comments']),
                \array_column($export['tokens']['tokens'], 'name'),
                \array_column($export['activity'], 'action'),
            ],
        );
    }

    #[Test]
    public function it_holds_nothing_of_another_member_but_the_display_name(): void
    {
        $client = self::createClient();
        $jan = self::actingAs($client);
        $piet = self::piet();
        self::sharedRound($jan, $piet);

        $client->request('GET', '/api/v1/me/export');

        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Piet V.', $body);
        foreach (['piet@example.org', 'Piet de Vries', '020 999 8888', 'Kerkstraat 9', $piet->id()->toRfc4122(), 'Tot de volgende keer.'] as $ofPiet) {
            self::assertStringNotContainsString($ofPiet, $body);
        }
    }

    #[Test]
    public function it_never_holds_the_secret_of_a_token(): void
    {
        $client = self::createClient();
        $jan = self::actingAs($client);
        self::sharedRound($jan, self::piet());

        $client->request('GET', '/api/v1/me/export');

        self::assertStringNotContainsString(\substr(self::SECRET, 4), (string) $client->getResponse()->getContent());
    }

    #[Test]
    public function it_refuses_a_visitor_who_is_not_logged_in(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/v1/me/export');

        self::assertResponseStatusCodeSame(401);
    }

    private static function piet(): Member
    {
        $piet = MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'piet@example.org', 'Piet de Vries', 'Piet V.');
        $piet->changeContactDetails(PhoneNumber::fromString('020 999 8888'), 'Kerkstraat 9');

        return $piet;
    }

    /**
     * A walked round of both, a comment by each, a token of the member, and an entry of the member about the other.
     */
    private static function sharedRound(Member $jan, Member $piet): void
    {
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable('2026-11-14T20:00:00+00:00')),
            $piet->id(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        );
        $round->signUp($jan->id(), new DateTimeImmutable('2026-11-02T12:00:00+00:00'));
        self::service(InMemoryRoundRepository::class)->add($round);
        $comments = self::service(InMemoryRoundCommentRepository::class);
        $comments->add(RoundComment::post($round, $jan->id(), 'Mooie avond.', new DateTimeImmutable('2026-11-15T09:00:00+00:00')));
        $comments->add(RoundComment::post($round, $piet->id(), 'Tot de volgende keer.', new DateTimeImmutable('2026-11-15T10:00:00+00:00')));
        self::service(InMemoryApiTokenRepository::class)->add(ApiToken::issue(
            $jan->id(),
            new TokenTerms('Script', [TokenScope::Read], new DateTimeImmutable('2027-01-01T00:00:00+00:00')),
            self::SECRET,
            new DateTimeImmutable('2026-11-01T10:00:00+00:00'),
        ));
        self::service(RecordingAuditEntryRepository::class)->add(AuditEntry::of(
            new AuditedAction('member.changed', 'member', $piet->id(), ['name' => ['old' => 'Piet de Vries', 'new' => 'Piet de Vries-Bakker']]),
            $jan->id(),
            'Ingelogd L.',
            Channel::Web,
            new DateTimeImmutable('2026-11-02T12:00:00+00:00'),
        ));
    }
}
