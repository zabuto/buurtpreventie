<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use App\Settings\UseCase\ChangeSettings\ChangeSettings;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\Role;
use DateTimeImmutable;
use DateTimeZone;
use Override;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * The hard cases of the plan, one of each: rounds around the last clock change, a last walker who withdraws, an
 * erased member in the history, a member without consent, rounds just under the minimum, at the maximum and with one
 * spot left, two members who share their full name, and an association with a name at the limit of the setting and a
 * short name of two letters.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final readonly class EdgeSet implements FixtureSet
{
    /** Exactly 100 characters, the most the setting takes. */
    private const string LONG_NAME = 'Stichting Buurtpreventie en Bewonersparticipatie voor de Wijken Noordoost, Zuidwest en de Binnenstad';

    public function __construct(
        private MemberSteps $members,
        private PatrolSteps $patrol,
        private SettingsSteps $settings,
    ) {
    }

    #[Override]
    public function load(DateTimeImmutable $now): array
    {
        $clockChange = self::lastClockChange($now);
        $start = $clockChange->modify('-30 days');

        $admin = $this->members->firstAdmin($start, new SampleProfile('beheer@example.org', 'Greet Hofman', 'Greet', '06 0000 0101'));
        $this->settings->change($admin, $start, new ChangeSettings(associationName: self::LONG_NAME, associationShortName: 'BP'));
        $meetingPoint = $this->patrol->meetingPoint($admin, $start, 'Buurthuis');
        $outcomes = $this->patrol->standardOutcomes($admin, $start);
        $people = $this->people($admin, $start);

        // 22:00 on the day before the clock change and on the day itself, both walked by Erik, who is erased later.
        foreach ([-1, 0] as $days) {
            $startsAt = SampleCalendar::day($clockChange, $days, '22:00');
            $round = $this->patrol->createRound($people['erik'], $startsAt->modify('-3 days'), $startsAt, $meetingPoint);
            $this->patrol->signUp($people['isil'], $startsAt->modify('-2 days'), $round);
            $this->patrol->report($people['isil'], $startsAt->modify('+2 hours'), $round, $outcomes[PatrolSteps::NOTHING_TO_REPORT], null);
        }

        $lonely = SampleCalendar::day($now, -3, '21:00');
        $this->patrol->createRound($people['karin'], $lonely->modify('-4 days'), $lonely, $meetingPoint);

        $this->upcoming($people, $now->modify('-2 days'), $now, $meetingPoint);

        $this->members->erase($admin, $people['erik'], $now->modify('-1 hour'));

        return [];
    }

    /**
     * Two members share their full name; Karin keeps her contact details to herself; Işıl and Michał have letters the
     * lookup key keeps as they are.
     *
     * @return array{
     *     bram: SampleMember,
     *     jan: SampleMember,
     *     janToo: SampleMember,
     *     karin: SampleMember,
     *     erik: SampleMember,
     *     isil: SampleMember,
     *     gulsen: SampleMember,
     *     tess: SampleMember,
     *     michal: SampleMember,
     * }
     */
    private function people(SampleMember $admin, DateTimeImmutable $at): array
    {
        $add = fn(SampleProfile $profile): SampleMember => $this->members->add($admin, $at, $profile);
        $people = [
            'bram' => $add(new SampleProfile('bram@example.org', 'Bram Koster', 'Bram', '06 0000 0102', [Role::Walker, Role::Coordinator])),
            'jan' => $add(new SampleProfile('jan.j@example.org', 'Jan Jansen', 'Jan J.', '06 0000 0103')),
            'janToo' => $add(new SampleProfile('jan.ja@example.org', 'Jan Jansen', 'Jan Ja.', '06 0000 0104')),
            'karin' => $add(new SampleProfile('karin@example.org', 'Karin Smeets', 'Karin', '06 0000 0105')),
            'erik' => $add(new SampleProfile('erik@example.org', 'Erik Wolters', 'Erik', '06 0000 0106')),
            'isil' => $add(new SampleProfile('isil@example.org', 'Işıl Aydın', 'Işıl', '06 0000 0107')),
            'gulsen' => $add(new SampleProfile('gulsen@example.org', 'Gülşen Öztürk', 'Gülşen', '06 0000 0108')),
            'tess' => $add(new SampleProfile('tess@example.org', 'Tess Bakker', 'Tess', '06 0000 0109')),
            'michal' => $add(new SampleProfile('michal@example.org', 'Michał Łęcki', 'Michał', '06 0000 0110')),
        ];
        foreach ($people as $key => $member) {
            if ('karin' === $key) {
                continue;
            }
            $this->members->consentToContactShare($member, $at);
        }

        return $people;
    }

    /**
     * Just under the minimum, at the maximum, one spot left, and a round whose last walker withdraws.
     *
     * @param array{
     *     bram: SampleMember,
     *     jan: SampleMember,
     *     janToo: SampleMember,
     *     karin: SampleMember,
     *     erik: SampleMember,
     *     isil: SampleMember,
     *     gulsen: SampleMember,
     *     tess: SampleMember,
     *     michal: SampleMember,
     * } $people
     */
    private function upcoming(array $people, DateTimeImmutable $created, DateTimeImmutable $now, string $meetingPoint): void
    {
        $this->patrol->createRound($people['gulsen'], $created, SampleCalendar::day($now, 3, '21:00'), $meetingPoint);

        $team = [$people['bram'], $people['jan'], $people['janToo'], $people['isil'], $people['tess'], $people['michal']];
        $this->staffed([...$team, $people['gulsen']], $created, SampleCalendar::day($now, 5, '21:00'), $meetingPoint);
        $this->staffed($team, $created, SampleCalendar::day($now, 6, '20:30'), $meetingPoint);

        $abandoned = $this->patrol->createRound($people['tess'], $created, SampleCalendar::day($now, 8, '21:00'), $meetingPoint);
        $this->patrol->withdraw($people['tess'], $created->modify('+1 hour'), $abandoned);
    }

    /**
     * @param non-empty-list<SampleMember> $walkers the creator first
     */
    private function staffed(array $walkers, DateTimeImmutable $created, DateTimeImmutable $startsAt, string $meetingPoint): void
    {
        $round = $this->patrol->createRound($walkers[0], $created, $startsAt, $meetingPoint);
        foreach (\array_slice($walkers, 1) as $minutes => $walker) {
            $this->patrol->signUp($walker, $created->modify(\sprintf('+%d minutes', $minutes + 1)), $round);
        }
    }

    /**
     * The last clock change two days back at least, so the round on its evening has been walked and reported. The
     * clocks in the local zone change on the last Sunday of March and of October.
     */
    private static function lastClockChange(DateTimeImmutable $now): DateTimeImmutable
    {
        $zone = new DateTimeZone(LocalTime::ZONE);
        $latest = $now->modify('-2 days');
        $year = (int) $latest->setTimezone($zone)->format('Y');

        $last = new DateTimeImmutable(\sprintf('last sunday of october %d 12:00', $year - 1), $zone);
        foreach (['march', 'october'] as $month) {
            $change = new DateTimeImmutable(\sprintf('last sunday of %s %d 12:00', $month, $year), $zone);
            if ($change <= $latest) {
                $last = $change;
            }
        }

        return $last;
    }
}
