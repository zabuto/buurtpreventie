<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Notification;

use App\Membership\Domain\Member;
use App\Shared\Domain\Notification;
use App\Shared\Infrastructure\Notification\MailNotifier;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\RecordingLogger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * The mails a member needs to log in, as plain Dutch text through the mailer of the app. The test environment sends
 * them to the null transport, where Symfony keeps them for the assertions; InMemoryServices gives the notifier a
 * sender of its own and a base URL under a path.
 */
#[Group('functional')]
final class MailNotifierTest extends ApiTestCase
{
    #[Test]
    public function it_mails_the_login_code_large_and_on_top_but_not_in_the_subject(): void
    {
        self::bootKernel();
        $jan = self::member();

        self::service(MailNotifier::class)->send(new Notification($jan->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60]));

        self::assertEmailCount(1);
        $mail = self::sentMail();
        self::assertSame(
            [['noreply@example.org'], ['jan@example.org'], 'Je inlogcode', null],
            [self::addresses($mail->getFrom()), self::addresses($mail->getTo()), $mail->getSubject(), $mail->getHtmlBody()],
        );
        self::assertSame(
            "Je inlogcode is:\n\n    482 913\n\nVul deze code in op het scherm waar je hem aanvroeg. De code werkt 60 minuten en één keer.\n\nHeb je geen code aangevraagd? Dan hoef je niets te doen.\n",
            $mail->getTextBody(),
        );
    }

    #[Test]
    public function it_mails_the_invitation_with_the_secret_behind_the_hash_of_a_link_under_the_path_of_the_base_url(): void
    {
        self::bootKernel();
        $jan = self::member();

        self::service(MailNotifier::class)->send(new Notification($jan->id(), 'account.invitation', [
            // @mago-expect lint:no-literal-password - a made-up secret the link must carry
            'secret' => 'bpi_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
            'hours' => 48,
        ]));

        $mail = self::sentMail();
        self::assertSame(['jan@example.org'], self::addresses($mail->getTo()));
        self::assertSame('Je bent uitgenodigd', $mail->getSubject());
        self::assertSame(
            "Je bent uitgenodigd voor de app van de buurtpreventie. Open deze link om in te loggen:\n\n"
                . "https://example.org/buurtpreventie/app/#/welcome/bpi_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef\n\n"
                . "De link werkt 48 uur en één keer. Werkt hij niet meer? Vraag dan op het inlogscherm een inlogcode aan met dit e-mailadres.\n",
            $mail->getTextBody(),
        );
    }

    #[Test]
    public function it_mails_nothing_to_a_deactivated_member_and_logs_that_without_the_parameters(): void
    {
        self::bootKernel();
        $jan = self::member();
        $jan->deactivate();

        self::service(MailNotifier::class)->send(new Notification($jan->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60]));

        self::assertEmailCount(0);
        self::assertStringNotContainsString('482 913', \json_encode(self::service(RecordingLogger::class)->records, \JSON_THROW_ON_ERROR));
        self::assertContains('No address for notification {type} for member {recipient}', \array_column(
            self::service(RecordingLogger::class)->records,
            'message',
        ));
    }

    /**
     * Messages about rounds get their mail in phase 3; until then they only reach the log.
     */
    #[Test]
    public function it_logs_any_other_message_instead_of_mailing_it(): void
    {
        self::bootKernel();
        $jan = self::member();

        self::service(MailNotifier::class)->send(new Notification($jan->id(), 'round.walker_withdrawn', ['roundId' => 'r-1']));

        self::assertEmailCount(0);
        self::assertSame(
            [[
                'level' => 'info',
                'message' => 'Notification {type} for member {recipient}',
                'context' => ['type' => 'round.walker_withdrawn', 'recipient' => $jan->id()->toRfc4122()],
            ]],
            self::service(RecordingLogger::class)->records,
        );
    }

    private static function member(): Member
    {
        return MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'jan@example.org', 'Jan Bakker', 'Jan B.');
    }

    private static function sentMail(): Email
    {
        $mail = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $mail);

        return $mail;
    }

    /**
     * @param array<array-key, Address> $addresses
     *
     * @return list<string>
     */
    private static function addresses(array $addresses): array
    {
        return \array_values(\array_map(static fn(Address $address): string => $address->getAddress(), $addresses));
    }
}
