<?php

declare(strict_types=1);

namespace App\Tests\Patrol\Infrastructure\Mail;

use App\Membership\Domain\Member;
use App\Patrol\Domain\Catalogue\MeetingPoint;
use App\Patrol\Domain\Round\Round;
use App\Patrol\Domain\Round\RoundStart;
use App\Patrol\Domain\Round\StaffingRule;
use App\Settings\Domain\Setting;
use App\Shared\Domain\Notification;
use App\Shared\Infrastructure\Notification\MailNotifier;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Patrol\Fake\InMemoryMeetingPointRepository;
use App\Tests\Patrol\Fake\InMemoryRoundRepository;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Support\ApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Email;

/**
 * The four mails about a round. Each names the date, time, daypart and meeting point, and how many others walk, never
 * who: who walks is in the app, behind the link every mail has. The round starts on Friday 20 November 2026 at 21:00
 * UTC, 22:00 on the local clock.
 */
#[Group('functional')]
final class RoundMailsTest extends ApiTestCase
{
    private const string NOTE = 'Sleutel bij Piet de Vries, 06 9876 5432';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function subjects(): iterable
    {
        yield 'reminder in Dutch' => ['round.reminder', 'nl', 'Je loopt vrijdag 20 november'];
        yield 'reminder in English' => ['round.reminder', 'en', 'You walk on Friday 20 November'];
        yield 'buddy needed in Dutch' => ['round.buddy_needed', 'nl', 'Loop je mee op vrijdag 20 november?'];
        yield 'buddy needed in English' => ['round.buddy_needed', 'en', 'Will you walk on Friday 20 November?'];
        yield 'walker withdrawn in Dutch' => ['round.walker_withdrawn', 'nl', 'Je ronde van vrijdag 20 november heeft te weinig lopers'];
        yield 'walker withdrawn in English' => ['round.walker_withdrawn', 'en', 'Your round on Friday 20 November is short of walkers'];
        yield 'cancelled in Dutch' => ['round.cancelled', 'nl', 'De ronde van vrijdag 20 november gaat niet door'];
        yield 'cancelled in English' => ['round.cancelled', 'en', 'The round on Friday 20 November is off'];
    }

    #[Test]
    #[DataProvider('subjects')]
    public function it_mails_each_message_about_a_round_in_html_and_text_in_the_language_of_the_association(
        string $type,
        string $language,
        string $subject,
    ): void {
        self::bootKernel();
        self::service(InMemorySettingRepository::class)->add(Setting::of('app.language', $language));
        [$anna, , $round] = self::world();

        self::service(MailNotifier::class)->send(self::notification($type, $anna, $round));

        $mail = self::sentMail();
        $time = 'nl' === $language ? 'vrijdag 20 november om 22:00' : 'Friday 20 November at 22:00';
        self::assertSame(
            [$subject, true, true],
            [$mail->getSubject(), \str_contains((string) $mail->getTextBody(), $time), \str_contains(self::visibleText($mail), $time)],
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function typesWithTheRound(): iterable
    {
        yield 'reminder' => ['round.reminder'];
        yield 'buddy needed' => ['round.buddy_needed'];
        yield 'walker withdrawn' => ['round.walker_withdrawn'];
    }

    /**
     * Review focus 5: the link keeps the path of the base URL.
     */
    #[Test]
    #[DataProvider('typesWithTheRound')]
    public function it_links_to_the_round_under_the_path_of_the_base_url(string $type): void
    {
        self::bootKernel();
        [$anna, , $round] = self::world();

        self::service(MailNotifier::class)->send(self::notification($type, $anna, $round));

        $link = 'https://example.org/buurtpreventie/app/#/rounds/' . $round->id()->toRfc4122();
        $mail = self::sentMail();
        self::assertSame([true, true], [
            \str_contains((string) $mail->getTextBody(), $link),
            \str_contains((string) $mail->getHtmlBody(), 'href="' . $link . '"'),
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function types(): iterable
    {
        yield 'reminder' => ['round.reminder'];
        yield 'buddy needed' => ['round.buddy_needed'];
        yield 'walker withdrawn' => ['round.walker_withdrawn'];
        yield 'cancelled' => ['round.cancelled'];
    }

    /**
     * Review focus 3: a mail gets forwarded and read on a locked phone, so it holds nothing of any member.
     */
    #[Test]
    #[DataProvider('types')]
    public function it_names_no_member_phone_number_address_or_note(string $type): void
    {
        self::bootKernel();
        [$anna, $piet, $round] = self::world();

        self::service(MailNotifier::class)->send(self::notification($type, $anna, $round));

        $mail = self::sentMail();
        $content = $mail->getSubject() . $mail->getTextBody() . $mail->getHtmlBody();
        $found = \array_filter(
            ['Anna Hughes', 'Anna H.', 'Piet de Vries', 'Piet V.', 'anna@example.org', 'piet@example.org', '06 1234 5678', 'Dorpsstraat 1', self::NOTE],
            static fn(string $personal): bool => \str_contains($content, $personal),
        );
        self::assertSame([], \array_values($found));
    }

    #[Test]
    public function it_tells_a_walker_how_many_others_walk_and_where_the_round_starts(): void
    {
        self::bootKernel();
        [$anna, , $round] = self::world();

        self::service(MailNotifier::class)->send(self::notification('round.reminder', $anna, $round));

        self::assertStringContainsString("22:00, avondronde, startpunt Kerkplein.\nEr loopt 1 ander mee.", (string) self::sentMail()->getTextBody());
    }

    #[Test]
    public function it_tells_a_walker_who_walks_alone_that_the_round_seeks_a_buddy(): void
    {
        self::bootKernel();
        [$anna, $piet, $round] = self::world();
        $round->withdraw($piet->id(), new DateTimeImmutable('2026-11-15T10:00:00+00:00'));

        self::service(MailNotifier::class)->send(new Notification($anna->id(), 'round.reminder', [
            'roundIds' => $round->id()->toRfc4122(),
            'seekingBuddyRoundIds' => $round->id()->toRfc4122(),
        ]));

        self::assertStringContainsString('Je loopt nog alleen. Deze ronde zoekt een loopmaatje.', (string) self::sentMail()->getTextBody());
    }

    /**
     * Review focus 2: on 28 March 2027 the clock goes forward, and 22:00 local is 20:00 UTC.
     */
    #[Test]
    public function it_gives_the_time_on_the_local_clock_on_the_day_the_clock_changes(): void
    {
        self::bootKernel();
        $anna = MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'anna@example.org', 'Anna Hughes', 'Anna H.');
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable('2027-03-28T20:00:00+00:00')),
            $anna->id(),
            new StaffingRule(minimumWalkers: 1, maximumWalkers: 7),
            new DateTimeImmutable('2026-11-10T09:00:00+00:00'),
        );
        self::service(InMemoryRoundRepository::class)->add($round);

        self::service(MailNotifier::class)->send(self::notification('round.reminder', $anna, $round));

        self::assertStringContainsString('zondag 28 maart om 22:00, avondronde.', (string) self::sentMail()->getTextBody());
    }

    /**
     * The member chose these messages, so the mail says why it came and where to turn it off.
     */
    #[Test]
    public function it_tells_a_member_asked_to_walk_why_and_where_to_turn_that_off(): void
    {
        self::bootKernel();
        [$anna, , $round] = self::world();

        self::service(MailNotifier::class)->send(self::notification('round.buddy_needed', $anna, $round));

        self::assertStringContainsString('https://example.org/buurtpreventie/app/#/account', (string) self::sentMail()->getTextBody());
    }

    /**
     * @return array{Member, Member, Round} Anna and Piet, both walking the round, with phone, address and a note
     */
    private static function world(): array
    {
        $members = self::service(InMemoryMemberRepository::class);
        $anna = MemberFixture::stored($members, 'anna@example.org', 'Anna Hughes', 'Anna H.');
        $piet = MemberFixture::stored($members, 'piet@example.org', 'Piet de Vries', 'Piet V.');
        $meetingPoint = MeetingPoint::create('Kerkplein');
        self::service(InMemoryMeetingPointRepository::class)->add($meetingPoint);
        $round = Round::plan(
            new RoundStart(new DateTimeImmutable('2026-11-20T21:00:00+00:00'), $meetingPoint, self::NOTE),
            $anna->id(),
            new StaffingRule(minimumWalkers: 2, maximumWalkers: 7),
            new DateTimeImmutable('2026-11-10T09:00:00+00:00'),
        );
        $round->signUp($piet->id(), new DateTimeImmutable('2026-11-10T09:00:00+00:00'));
        self::service(InMemoryRoundRepository::class)->add($round);

        return [$anna, $piet, $round];
    }

    private static function notification(string $type, Member $recipient, Round $round): Notification
    {
        $id = $round->id()->toRfc4122();

        return new Notification($recipient->id(), $type, match ($type) {
            'round.reminder' => ['roundIds' => $id, 'seekingBuddyRoundIds' => ''],
            'round.cancelled' => ['roundId' => $id, 'startsAt' => $round->startsAt()->format(\DATE_ATOM)],
            default => ['roundId' => $id],
        });
    }

    private static function sentMail(): Email
    {
        self::assertEmailCount(1);
        $mail = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $mail);

        return $mail;
    }

    /**
     * The text a reader sees in the HTML part, with tags and line breaks gone.
     */
    private static function visibleText(Email $mail): string
    {
        return (string) \preg_replace('/\s+/', ' ', \html_entity_decode(\strip_tags((string) $mail->getHtmlBody())));
    }
}
