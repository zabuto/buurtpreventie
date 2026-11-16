<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Audit\Domain\AuditEntry;
use App\Identity\Domain\ApiToken;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Membership\Domain\Member;
use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Catalogue\Outcome;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundComment;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\Setting;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Shared\Infrastructure\Security\MemberUser;
use App\Shared\Infrastructure\Security\TokenExecutionContext;
use App\Tests\Audit\Fake\RecordingAuditEntryRepository;
use App\Tests\Identity\Fake\InMemoryApiTokenRepository;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Patrol\Domain\Round\RoundFixture;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Patrol\Fake\InMemoryOutcomeRepository;
use App\Tests\Patrol\Fake\InMemoryRoundCommentRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Settings\Fake\InMemoryBrandImages;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Settings\Fake\TestImages;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Every route against every principal over HTTP, next to the matrix of the voters: nobody logged in, a member without
 * a role, and a member with exactly one role. A principal that may not act gets 401 or 403; one that may gets
 * anything else, a 404, 409 or 422 included, or the 403 for a login that is not recent, which comes after the voters.
 * Each request goes to data that exists, with a valid body, so a refusal can only come from the rights. Where a right
 * depends on a relation, the row takes the data of someone else; the voter matrix covers the relations.
 */
#[Group('functional')]
final class HttpMatrixTest extends ApiTestCase
{
    private const array MEMBERS = ['no role', 'walker', 'coordinator', 'analyst', 'admin'];
    private const array EVERYONE = ['nobody', 'no role', 'walker', 'coordinator', 'analyst', 'admin'];
    private const array ROLES = [
        'no role' => [],
        'walker' => [Role::Walker],
        'coordinator' => [Role::Coordinator],
        'analyst' => [Role::Analyst],
        'admin' => [Role::Admin],
    ];
    private const string PERIOD = 'from=2026-11-01T00:00:00Z&to=2026-12-01T00:00:00Z';

    // @mago-expect lint:no-literal-password - a made-up password nobody has
    private const string WRONG_PASSWORD = 'geen-wachtwoord';

    // @mago-expect lint:no-literal-password - a made-up new password, refused for want of a recent login
    private const string NEW_PASSWORD = 'een-lang-nieuw-wachtwoord';

    /**
     * Per route: method, path, JSON body, the bytes of a file or null, and the principals let through. A path names the
     * data of the world in braces: {round} ahead and {pastRound} walked, both of Piet; {piet}; {report} and {comment}
     * of Piet on the walked round; {outcome}; {meetingPoint}; {token} of the principal; {logo}, a file of the logo.
     *
     * @return array<string, array{string, string, array<string, mixed>|string|null, list<string>}>
     */
    private static function rows(): array
    {
        return [
            ...self::publicRows(),
            ...self::ownAccountRows(),
            ...self::roundRows(),
            ...self::afterRoundRows(),
            ...self::adminRows(),
        ];
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>|string|null, list<string>}>
     */
    private static function publicRows(): array
    {
        return [
            'maintenance_check_health' => ['GET', '/api/v1/health', null, self::EVERYONE],
            'api_v1_docs' => ['GET', '/api/v1/docs.json', null, self::EVERYONE],
            'auth_login' => ['POST', '/api/v1/auth/login', ['email' => 'niemand@example.org', 'password' => self::WRONG_PASSWORD], self::EVERYONE],
            'auth_logout' => ['POST', '/api/v1/auth/logout', null, self::EVERYONE],
            'identity_request_login_code' => ['POST', '/api/v1/auth/login-code', ['email' => 'niemand@example.org'], self::EVERYONE],
            'auth_login_code_verify' => ['POST', '/api/v1/auth/login-code/verify', ['challengeId' => \str_repeat('0', 32), 'code' => '123456'], self::EVERYONE],
            'auth_invitation' => ['POST', '/api/v1/auth/invitation', ['secret' => 'bpi_' . \str_repeat('0', 64)], self::EVERYONE],
            'auth_confirm' => ['POST', '/api/v1/auth/confirm', ['password' => self::WRONG_PASSWORD], self::MEMBERS],
            'settings_get_brand_image' => ['GET', '/api/v1/media/brand/{logo}', null, self::EVERYONE],
        ];
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>|string|null, list<string>}>
     */
    private static function ownAccountRows(): array
    {
        return [
            'membership_get_own_profile' => ['GET', '/api/v1/me', null, self::MEMBERS],
            'membership_change_own_contact_details' => ['PATCH', '/api/v1/me', ['phone' => '06 9999 0000'], self::MEMBERS],
            'identity_set_password' => ['PUT', '/api/v1/me/password', ['password' => self::NEW_PASSWORD], self::MEMBERS],
            'identity_list_api_tokens' => ['GET', '/api/v1/me/tokens', null, self::MEMBERS],
            'identity_create_api_token' => [
                'POST',
                '/api/v1/me/tokens',
                ['name' => 'Script', 'scopes' => ['read'], 'expiresAt' => '2027-01-01T00:00:00+01:00'],
                self::MEMBERS,
            ],
            'identity_revoke_api_token' => ['DELETE', '/api/v1/me/tokens/{token}', null, self::MEMBERS],
            'audit_list_own_activity' => ['GET', '/api/v1/me/activity', null, self::MEMBERS],
            'membership_export_own_data' => ['GET', '/api/v1/me/export', null, self::MEMBERS],
            'patrol_list_my_rounds' => ['GET', '/api/v1/me/rounds', null, self::MEMBERS],
            'membership_get_member_summaries' => ['GET', '/api/v1/members', null, self::MEMBERS],
            'patrol_list_meeting_points' => ['GET', '/api/v1/meeting-points', null, self::MEMBERS],
            'patrol_list_outcomes' => ['GET', '/api/v1/outcomes', null, self::MEMBERS],
        ];
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>|string|null, list<string>}>
     */
    private static function roundRows(): array
    {
        return [
            'patrol_list_rounds' => ['GET', '/api/v1/rounds?' . self::PERIOD, null, self::MEMBERS],
            'patrol_get_round' => ['GET', '/api/v1/rounds/{round}', null, self::MEMBERS],
            'patrol_find_staffing_gaps' => ['GET', '/api/v1/staffing-gaps', null, self::MEMBERS],
            'patrol_create_round' => ['POST', '/api/v1/rounds', ['startsAt' => '2026-11-25T22:00:00+01:00'], ['walker']],
            'patrol_change_round' => ['PATCH', '/api/v1/rounds/{round}', ['startsAt' => '2026-11-25T22:00:00+01:00'], ['coordinator']],
            'patrol_delete_round' => ['DELETE', '/api/v1/rounds/{round}', null, ['coordinator']],
            'patrol_cancel_round' => ['POST', '/api/v1/rounds/{pastRound}/cancellation', null, ['coordinator']],
            'patrol_sign_up_for_round' => ['PUT', '/api/v1/rounds/{round}/walkers/me', null, ['walker']],
            'patrol_withdraw_from_round' => ['DELETE', '/api/v1/rounds/{round}/walkers/me', null, ['walker']],
            'patrol_sign_up_member_for_round' => ['PUT', '/api/v1/rounds/{round}/walkers/{piet}', null, ['coordinator']],
            'patrol_withdraw_member_from_round' => ['DELETE', '/api/v1/rounds/{round}/walkers/{piet}', null, ['coordinator']],
        ];
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>|string|null, list<string>}>
     */
    private static function afterRoundRows(): array
    {
        return [
            'patrol_report_round_result' => ['POST', '/api/v1/rounds/{pastRound}/reports', ['outcomeId' => '{outcome}'], ['coordinator']],
            'patrol_delete_round_report' => ['DELETE', '/api/v1/reports/{report}', null, ['coordinator']],
            'patrol_add_round_comment' => ['POST', '/api/v1/rounds/{pastRound}/comments', ['text' => 'Mooie avond.'], ['coordinator']],
            'patrol_delete_round_comment' => ['DELETE', '/api/v1/comments/{comment}', null, ['coordinator']],
            'patrol_list_round_reports' => ['GET', '/api/v1/reports?' . self::PERIOD, null, ['coordinator', 'analyst']],
            'audit_get_round_history' => ['GET', '/api/v1/rounds/{round}/history', null, ['coordinator']],
        ];
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>|string|null, list<string>}>
     */
    private static function adminRows(): array
    {
        return [
            'membership_list_members' => ['GET', '/api/v1/admin/members', null, ['admin']],
            'membership_get_member' => ['GET', '/api/v1/admin/members/{piet}', null, ['admin']],
            'membership_add_member' => [
                'POST',
                '/api/v1/admin/members',
                ['email' => 'kees@example.org', 'name' => 'Kees de Wit', 'displayName' => 'Kees W.'],
                ['admin'],
            ],
            'membership_change_member' => ['PATCH', '/api/v1/admin/members/{piet}', ['certified' => true], ['admin']],
            'membership_change_member_roles' => ['PUT', '/api/v1/admin/members/{piet}/roles', ['roles' => ['walker']], ['admin']],
            'membership_deactivate_member' => ['POST', '/api/v1/admin/members/{piet}/deactivation', null, ['admin']],
            'membership_reactivate_member' => ['DELETE', '/api/v1/admin/members/{piet}/deactivation', null, ['admin']],
            'membership_erase_member' => ['DELETE', '/api/v1/admin/members/{piet}', null, ['admin']],
            'membership_invite_member' => ['POST', '/api/v1/admin/members/{piet}/invitation', null, ['coordinator', 'admin']],
            'patrol_list_all_meeting_points' => ['GET', '/api/v1/admin/meeting-points', null, ['admin']],
            'patrol_create_meeting_point' => [
                'POST',
                '/api/v1/admin/meeting-points',
                ['name' => 'Kerkplein', 'latitude' => null, 'longitude' => null],
                ['admin'],
            ],
            'patrol_change_meeting_point' => ['PATCH', '/api/v1/admin/meeting-points/{meetingPoint}', ['name' => 'Kerkplein'], ['admin']],
            'patrol_archive_meeting_point' => ['POST', '/api/v1/admin/meeting-points/{meetingPoint}/archive', null, ['admin']],
            'patrol_restore_meeting_point' => ['DELETE', '/api/v1/admin/meeting-points/{meetingPoint}/archive', null, ['admin']],
            'patrol_list_all_outcomes' => ['GET', '/api/v1/admin/outcomes', null, ['admin']],
            'patrol_create_outcome' => ['POST', '/api/v1/admin/outcomes', ['name' => 'Verdacht', 'kind' => 'incident'], ['admin']],
            'patrol_change_outcome' => ['PATCH', '/api/v1/admin/outcomes/{outcome}', ['name' => 'Niets bijzonders'], ['admin']],
            'patrol_archive_outcome' => ['POST', '/api/v1/admin/outcomes/{outcome}/archive', null, ['admin']],
            'patrol_restore_outcome' => ['DELETE', '/api/v1/admin/outcomes/{outcome}/archive', null, ['admin']],
            'audit_list_audit_entries' => ['GET', '/api/v1/admin/audit', null, ['admin']],
            'settings_get' => ['GET', '/api/v1/admin/settings', null, ['admin']],
            'settings_change' => ['PATCH', '/api/v1/admin/settings', ['associationName' => 'Buurtpreventie Oost'], ['admin']],
            'settings_change_brand_image' => ['PUT', '/api/v1/admin/settings/images/logo', TestImages::png(64, 32), ['admin']],
            'settings_remove_brand_image' => ['DELETE', '/api/v1/admin/settings/images/logo', null, ['admin']],
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function requests(): iterable
    {
        foreach (\array_keys(self::rows()) as $route) {
            foreach (self::EVERYONE as $principal) {
                yield $route . ' as ' . $principal => [$route, $principal];
            }
        }
    }

    #[DataProvider('requests')]
    #[Test]
    public function it_lets_exactly_the_allowed_principals_through(string $route, string $principal): void
    {
        [$method, $path, $body, $allowed] = self::rows()[$route];
        $client = self::createClient();
        $client->disableReboot();
        $actor = self::loggedIn($client, $principal);
        $world = self::world($actor);

        $client->request(
            $method,
            \strtr($path, $world),
            server: \is_array($body) ? ['CONTENT_TYPE' => 'application/json'] : [],
            content: \is_array($body) ? \strtr(\json_encode($body, \JSON_THROW_ON_ERROR), $world) : $body,
        );

        $expected = match (true) {
            \in_array($principal, $allowed, strict: true) => 'let through',
            'nobody' === $principal => 'unauthenticated',
            default => 'forbidden',
        };
        self::assertSame($expected, self::outcome($client), (string) $client->getResponse()->getContent());
    }

    /**
     * A route without a row would go untested; a row without a route tests nothing. The probes of the in-memory
     * kernel are no part of the app.
     */
    #[Test]
    public function it_has_a_row_for_every_route_of_the_api(): void
    {
        self::createClient();
        $router = self::getContainer()->get('router');
        // @phpstan-ignore staticMethod.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        self::assertInstanceOf(RouterInterface::class, $router);
        $routes = [];
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            if (\str_starts_with($route->getPath(), '/api/v1/') && !\str_starts_with((string) $name, 'test_probe_')) {
                $routes[] = $name;
            }
        }

        $rows = \array_keys(self::rows());
        \sort($routes);
        \sort($rows);
        self::assertSame($routes, $rows);
    }

    private static function loggedIn(KernelBrowser $client, string $principal): ?Member
    {
        if ('nobody' === $principal) {
            return null;
        }

        $member = MemberFixture::stored(
            self::service(InMemoryMemberRepository::class),
            'ingelogd@example.org',
            'Ingelogd Lid',
            'Ingelogd L.',
            self::ROLES[$principal] ?? [],
        );
        $client->loginUser(new MemberUser($member->id(), $member->roles()), 'browser', [TokenExecutionContext::CHANNEL => Channel::Web]);

        return $member;
    }

    /**
     * @return array<string, string> the value of each placeholder of the paths
     */
    private static function world(?Member $actor): array
    {
        $piet = MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'piet@example.org', 'Piet de Vries', 'Piet V.');
        $round = RoundFixture::withWalkers($piet->id(), 1);
        $pastRound = Round::plan(
            new RoundStart(new DateTimeImmutable('2026-11-14T21:00:00+00:00')),
            $piet->id(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 3),
            new DateTimeImmutable(RoundFixture::CREATED_AT),
        );
        $pastRound->signUp(Uuid::v7(), new DateTimeImmutable(RoundFixture::CREATED_AT));
        self::service(InMemoryRoundRepository::class)->add($round);
        self::service(InMemoryRoundRepository::class)->add($pastRound);

        $outcome = Outcome::create('Rustig', OutcomeKind::NothingToReport);
        self::service(InMemoryOutcomeRepository::class)->add($outcome);
        $report = $pastRound->submitReport($piet->id(), $outcome, 'Niets gezien', new DateTimeImmutable('2026-11-15T09:00:00+00:00'));
        $comment = RoundComment::post($pastRound, $piet->id(), 'Mooie avond.', new DateTimeImmutable('2026-11-15T09:00:00+00:00'));
        self::service(InMemoryRoundCommentRepository::class)->add($comment);
        $meetingPoint = MeetingPoint::create('Kerk');
        self::service(InMemoryMeetingPointRepository::class)->add($meetingPoint);
        self::service(RecordingAuditEntryRepository::class)->add(AuditEntry::of(
            new AuditedAction('round.created', 'round', $round->id()),
            $piet->id(),
            'Piet V.',
            Channel::Web,
            new DateTimeImmutable(RoundFixture::CREATED_AT),
        ));

        $logo = self::service(InMemoryBrandImages::class)->store(BrandImageKind::Logo, TestImages::png(64, 32));
        self::service(InMemorySettingRepository::class)->add(Setting::of('brand.logo', $logo));

        return [
            '{logo}' => BrandImageFile::of(BrandImageKind::Logo, 512, $logo)->name(),
            '{round}' => $round->id()->toRfc4122(),
            '{pastRound}' => $pastRound->id()->toRfc4122(),
            '{piet}' => $piet->id()->toRfc4122(),
            '{report}' => $report->id()->toRfc4122(),
            '{comment}' => $comment->id()->toRfc4122(),
            '{outcome}' => $outcome->id()->toRfc4122(),
            '{meetingPoint}' => $meetingPoint->id()->toRfc4122(),
            '{token}' => null === $actor ? Uuid::v7()->toRfc4122() : self::tokenOf($actor)->id()->toRfc4122(),
        ];
    }

    private static function tokenOf(Member $member): ApiToken
    {
        $token = ApiToken::issue(
            $member->id(),
            new TokenTerms('Script', [TokenScope::Read], new DateTimeImmutable('2027-01-01T00:00:00+00:00')),
            'bpt_' . \str_repeat('ab', 32),
            new DateTimeImmutable('2026-11-01T10:00:00+00:00'),
        );
        self::service(InMemoryApiTokenRepository::class)->add($token);

        return $token;
    }

    /**
     * Only the refusals of the firewall and the voters count as such; the 403 for a login that is not recent comes
     * after the voters let the member through.
     */
    private static function outcome(KernelBrowser $client): string
    {
        $status = $client->getResponse()->getStatusCode();
        if (401 !== $status && 403 !== $status) {
            return 'let through';
        }

        $type = self::json($client)['type'] ?? null;

        return match ($type) {
            'urn:buurtpreventie:problem:unauthorized' => 'unauthenticated',
            'urn:buurtpreventie:problem:access_denied' => 'forbidden',
            default => 'let through',
        };
    }
}
