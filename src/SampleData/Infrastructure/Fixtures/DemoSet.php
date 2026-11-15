<?php

declare(strict_types=1);

namespace App\SampleData\Infrastructure\Fixtures;

use App\Shared\Domain\Role;
use DateTimeImmutable;
use Override;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * A made-up neighbourhood watch that looks in use: twenty members, rounds from three months back to six weeks ahead,
 * mostly in the evening, more than a quarter without a buddy, a few full ones, reports with now and then an incident.
 * A fixed seed gives the same people and pattern on every load; only the dates follow today.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final readonly class DemoSet implements FixtureSet
{
    private const int SEED = 20_261_115;

    /**
     * The coordinator, who walks too: name, display name and e-mail address. A display name is the first name, with
     * an initial only where two share it, as in a group this size.
     *
     * @var array{string, string, string}
     */
    private const array COORDINATOR = ['Fatima el Amrani', 'Fatima', 'fatima.elamrani'];

    /** @var list<array{string, string, string}> */
    private const array WALKERS = [
        ['Jan Bakker',     'Jan B.',  'jan.bakker'],
        ['Jan Hendriks',   'Jan H.',  'jan.hendriks'],
        ['Sanne de Vries', 'Sanne',   'sanne.devries'],
        ['Priya Sharma',   'Priya',   'priya.sharma'],
        ['Anouk Mulder',   'Anouk',   'anouk.mulder'],
        ['Mehmet Yılmaz',  'Mehmet',  'mehmet.yilmaz'],
        ['Els van Dijk',   'Els',     'els.vandijk'],
        ['Łukasz Wójcik',  'Łukasz',  'lukasz.wojcik'],
        ['Lotte Bos',      'Lotte',   'lotte.bos'],
        ['Kerwin Esajas',  'Kerwin',  'kerwin.esajas'],
        ['Gülşen Öztürk',  'Gülşen',  'gulsen.ozturk'],
        ['Şükrü Doğan',    'Şükrü',   'sukru.dogan'],
        ['Marieke Peters', 'Marieke', 'marieke.peters'],
        ['Yıldız Kaya',    'Yıldız',  'yildiz.kaya'],
        ['Zoë Hoekstra',   'Zoë',     'zoe.hoekstra'],
        ['Willem Dekker',  'Willem',  'willem.dekker'],
        ['Yara Brouwer',   'Yara',    'yara.brouwer'],
    ];

    /** Priya reads the rounds into the agenda on her phone. */
    private const string USES_THE_API = 'priya.sharma';

    /** Willem stopped walking; deactivating him withdraws him from the rounds ahead. */
    private const string STOPPED = 'willem.dekker';

    public function __construct(
        private MemberSteps $members,
        private PatrolSteps $patrol,
        private DemoRounds $rounds,
    ) {
    }

    #[Override]
    public function load(DateTimeImmutable $now): void
    {
        $random = new Randomizer(new Mt19937(self::SEED));
        $start = SampleCalendar::day($now, DemoRounds::FIRST_DAY - 30, '09:00');

        $admin = $this->members->firstAdmin($start, new SampleProfile('ingrid.deboer@example.org', 'Ingrid de Boer', 'Ingrid', '06 0000 0001'));
        $this->members->add($admin, $start, new SampleProfile('thomas.visser@example.org', 'Thomas Visser', 'Thomas', '06 0000 0002', [Role::Analyst]));
        $people = $this->walkers($random, $admin, $start);
        $neighbourhood = new Neighbourhood(
            $people['coordinator'],
            $people['walkers'],
            [
                $this->patrol->meetingPoint($admin, $start, 'Buurthuis De Kern'),
                $this->patrol->meetingPoint($admin, $start, 'Speeltuin Lindelaan'),
                $this->patrol->meetingPoint($admin, $start, 'Hoek Molenweg en Schoolstraat'),
            ],
            $this->patrol->standardOutcomes($admin, $start),
        );

        $this->rounds->play($random, $now, $neighbourhood);

        foreach ($people['stopped'] as $stopped) {
            $this->members->deactivate($admin, $stopped, $now->modify('-5 minutes'));
        }
    }

    /**
     * @return array{coordinator: SampleMember, walkers: list<SampleMember>, stopped: list<SampleMember>}
     */
    private function walkers(Randomizer $random, SampleMember $admin, DateTimeImmutable $at): array
    {
        [$name, $displayName, $address] = self::COORDINATOR;
        $coordinator = $this->walker(
            $random,
            $admin,
            $at,
            new SampleProfile(
                $address . '@example.org',
                $name,
                $displayName,
                '06 0000 0010',
                [
                    Role::Walker,
                    Role::Coordinator,
                ],
            ),
        );

        $walkers = [$coordinator];
        $stopped = [];
        foreach (self::WALKERS as $index => [$name, $displayName, $address]) {
            $walker = $this->walker(
                $random,
                $admin,
                $at,
                new SampleProfile($address . '@example.org', $name, $displayName, \sprintf('06 0000 %04d', $index + 11)),
            );
            if (self::USES_THE_API === $address) {
                $this->members->giveApiAccess($walker, $at->modify('+1 day'), 'Agenda op mijn telefoon');
            }
            if (self::STOPPED === $address) {
                $stopped[] = $walker;
            }
            $walkers[] = $walker;
        }

        return ['coordinator' => $coordinator, 'walkers' => $walkers, 'stopped' => $stopped];
    }

    /**
     * About half share their contact details and a third hear about rounds that seek a buddy.
     */
    private function walker(Randomizer $random, SampleMember $admin, DateTimeImmutable $at, SampleProfile $profile): SampleMember
    {
        $walker = $this->members->add($admin, $at, $profile);
        if ($random->getInt(0, 1) === 1) {
            $this->members->consentToContactShare($walker, $at);
        }
        if ($random->getInt(0, 2) === 0) {
            $this->members->notifyAboutOpenRounds($walker, $at);
        }

        return $walker;
    }
}
