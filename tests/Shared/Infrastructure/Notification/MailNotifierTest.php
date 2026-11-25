<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Notification;

use App\Membership\Domain\Member;
use App\Settings\Domain\BrandImageKind;
use App\Settings\Domain\Setting;
use App\Shared\Domain\Notification;
use App\Shared\Infrastructure\Mail\MailComposers;
use App\Shared\Infrastructure\Notification\MailNotifier;
use App\Tests\Membership\Fake\InMemoryMemberRepository;
use App\Tests\Membership\Fake\MemberFixture;
use App\Tests\Settings\Fake\InMemoryBrandImages;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Settings\Fake\TestImages;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\MailHtml;
use App\Tests\Support\RecordingLogger;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Every mail through the mailer of the app, in HTML and text with the branding of the association. The test
 * environment sends them to the null transport, where Symfony keeps them for the assertions; InMemoryServices gives
 * the notifier a sender of its own and a base URL under a path.
 */
#[Group('functional')]
final class MailNotifierTest extends ApiTestCase
{
    // @mago-expect lint:no-literal-password - a made-up secret the link must carry
    private const string SECRET = 'bpi_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    #[Test]
    public function it_mails_the_login_code_in_html_and_text_but_not_in_the_subject(): void
    {
        self::bootKernel();

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60]));

        $mail = self::sentMail();
        self::assertSame(['Je inlogcode', true, true], [
            $mail->getSubject(),
            \str_contains((string) $mail->getTextBody(), '482 913'),
            \str_contains((string) $mail->getHtmlBody(), '482 913'),
        ]);
    }

    /**
     * @return iterable<string, array{string, array<string, string|int>}>
     */
    public static function accountMails(): iterable
    {
        yield 'login code' => ['account.login_code', ['code' => '482 913', 'minutes' => 60]];
        yield 'invitation' => ['account.invitation', ['secret' => self::SECRET, 'hours' => 48]];
    }

    /**
     * @param array<string, string|int> $parameters
     */
    #[Test]
    #[DataProvider('accountMails')]
    public function it_styles_the_mail_only_with_css_that_mail_programs_keep(string $type, array $parameters): void
    {
        self::bootKernel();
        self::setting('brand.logo', self::service(InMemoryBrandImages::class)->store(BrandImageKind::Logo, TestImages::png(600, 200)));

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), $type, $parameters));

        MailHtml::assertOnlyRobustCss((string) self::sentMail()->getHtmlBody());
    }

    #[Test]
    public function it_mails_in_the_language_of_the_association(): void
    {
        self::bootKernel();
        self::setting('app.language', 'en');

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60]));

        self::assertSame('Your login code', self::sentMail()->getSubject());
    }

    #[Test]
    public function it_mails_the_invitation_with_the_secret_behind_the_hash_of_a_link_under_the_path_of_the_base_url(): void
    {
        self::bootKernel();

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'account.invitation', ['secret' => self::SECRET, 'hours' => 48]));

        $link = 'https://example.org/buurtpreventie/app/#/welcome/' . self::SECRET;
        $mail = self::sentMail();
        self::assertSame(
            ['Je bent uitgenodigd voor de app van Buurtpreventie', true, true],
            [$mail->getSubject(), \str_contains((string) $mail->getTextBody(), $link), \str_contains((string) $mail->getHtmlBody(), 'href="' . $link . '"')],
        );
    }

    /**
     * The member reads which token, when on the local clock, and what to do when it was not them, in both bodies.
     */
    #[Test]
    public function it_mails_which_token_reached_the_daily_limit_and_when_with_what_to_do_about_it(): void
    {
        self::bootKernel();

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'account.token_limit_reached', [
            'tokenName' => 'Claude Code',
            'reachedAt' => '2026-11-16T22:30:00+00:00',
        ]));

        $mail = self::sentMail();
        $intro = 'Op maandag 16 november om 23:30 was je token Claude Code vaker gebruikt dan op één dag mag.';
        $notYou = 'Was jij dit niet? Trek het token dan in en meld het aan de coördinator.';
        $link = 'https://example.org/buurtpreventie/app/#/account';
        self::assertSame(
            ['Je token Claude Code is vaker gebruikt dan mag', true, true, true, true, true, true],
            [
                $mail->getSubject(),
                \str_contains((string) $mail->getTextBody(), $intro),
                \str_contains((string) $mail->getTextBody(), $notYou),
                \str_contains((string) $mail->getTextBody(), $link),
                \str_contains((string) $mail->getHtmlBody(), $intro),
                \str_contains((string) $mail->getHtmlBody(), $notYou),
                \str_contains((string) $mail->getHtmlBody(), 'href="' . $link . '"'),
            ],
        );
    }

    /**
     * The expiry can be up to a year ahead, so it carries the year.
     */
    #[Test]
    public function it_mails_the_name_rights_and_expiry_of_a_new_token_with_what_to_do_about_it(): void
    {
        self::bootKernel();

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'account.token_created', [
            'tokenName' => 'Claude Code',
            'scopes' => 'read,write,admin',
            'expiresAt' => '2027-02-13T23:30:00+00:00',
        ]));

        $mail = self::sentMail();
        $lines = [
            'Er is een nieuw token gemaakt op jouw naam: Claude Code.',
            'Dit token mag lezen, wijzigen en beheren.',
            'Het werkt tot zondag 14 februari 2027.',
            'Was jij dit niet? Trek het token dan in en meld het aan de coördinator.',
        ];
        self::assertSame('Er is een nieuw token gemaakt op jouw naam', $mail->getSubject());
        foreach ($lines as $line) {
            self::assertStringContainsString($line, (string) $mail->getTextBody(), 'text: ' . $line);
            self::assertStringContainsString($line, (string) $mail->getHtmlBody(), 'html: ' . $line);
        }
    }

    #[Test]
    public function it_names_a_single_right_of_a_new_token_without_a_list(): void
    {
        self::bootKernel();
        self::setting('app.language', 'en');

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'account.token_created', [
            'tokenName' => 'Script',
            'scopes' => 'read',
            'expiresAt' => '2027-02-13T12:00:00+00:00',
        ]));

        self::assertStringContainsString('This token can read.', (string) self::sentMail()->getTextBody());
    }

    /**
     * The address is the one the mail server may send from, or SPF and DKIM fail; the association names the sender.
     */
    #[Test]
    public function it_sends_from_the_address_of_the_server_under_the_name_of_the_association(): void
    {
        self::bootKernel();
        self::setting('mail.sender_name', 'Eastside Watch');

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60]));

        $from = self::sentMail()->getFrom()[0] ?? null;
        self::assertSame(['noreply@example.org', 'Eastside Watch'], [$from?->getAddress(), $from?->getName()]);
    }

    #[Test]
    public function it_lets_a_member_reply_to_the_contact_of_the_association(): void
    {
        self::bootKernel();
        self::setting('contact.email', 'contact@example.org');

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60]));

        self::assertSame(['contact@example.org'], self::addresses(self::sentMail()->getReplyTo()));
    }

    #[Test]
    public function it_sets_no_reply_address_without_a_contact(): void
    {
        self::bootKernel();

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60]));

        self::assertSame([], self::sentMail()->getReplyTo());
    }

    #[Test]
    public function it_shows_the_name_of_the_association_as_text_without_a_logo(): void
    {
        self::bootKernel();
        self::setting('association.name', 'Eastside Watch');

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60]));

        $mail = self::sentMail();
        self::assertSame([true, false, []], [
            \str_contains((string) $mail->getHtmlBody(), 'Eastside Watch'),
            \str_contains((string) $mail->getHtmlBody(), 'cid:'),
            $mail->getAttachments(),
        ]);
    }

    /**
     * Embedded, not linked: a mail program then fetches nothing from the server, and the alt text keeps the name.
     */
    #[Test]
    public function it_embeds_the_logo_with_the_name_as_its_alternative_text(): void
    {
        self::bootKernel();
        self::setting('association.name', 'Eastside Watch');
        self::setting('brand.logo', self::service(InMemoryBrandImages::class)->store(BrandImageKind::Logo, TestImages::png(600, 200)));

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60]));

        $mail = self::sentMail();
        $logo = $mail->getAttachments()[0] ?? null;
        self::assertInstanceOf(DataPart::class, $logo);
        self::assertSame(
            ['inline', 'image/png', true],
            [
                $logo->getDisposition(),
                $logo->getMediaType() . '/' . $logo->getMediaSubtype(),
                \str_contains((string) $mail->getHtmlBody(), 'src="cid:' . $logo->getName() . '" alt="Eastside Watch"'),
            ],
        );
    }

    /**
     * The layout declares light and dark, so a mail program that follows dark mode leaves the colours to the mail; without
     * dark styles of its own the mail stays light on a dark screen.
     */
    #[Test]
    public function it_turns_dark_in_dark_mode_with_the_dark_primary_colour_of_the_association(): void
    {
        self::bootKernel();
        self::setting('brand.primary_dark', '#9ad3a5');

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60]));

        $html = (string) self::sentMail()->getHtmlBody();
        self::assertSame(
            [true, true, true],
            [
                \str_contains($html, '@media (prefers-color-scheme: dark)'),
                \str_contains($html, 'background-color:#121212 !important'),
                \str_contains($html, 'color:#9ad3a5 !important'),
            ],
        );
    }

    #[Test]
    public function it_mails_nothing_to_a_deactivated_member_and_logs_that_without_the_parameters(): void
    {
        self::bootKernel();
        $member = self::member();
        $member->deactivate();

        self::service(MailNotifier::class)->send(new Notification($member->id(), 'account.login_code', ['code' => '482 913', 'minutes' => 60]));

        self::assertEmailCount(0);
        self::assertStringNotContainsString('482 913', \json_encode(self::service(RecordingLogger::class)->records, \JSON_THROW_ON_ERROR));
        self::assertContains('No address for notification {type} for member {recipient}', \array_column(
            self::service(RecordingLogger::class)->records,
            'message',
        ));
    }

    #[Test]
    public function it_refuses_a_message_without_a_mail_of_its_own(): void
    {
        self::bootKernel();

        $this->expectException(LogicException::class);

        self::service(MailNotifier::class)->send(new Notification(self::member()->id(), 'round.unknown', []));
    }

    /**
     * A message type in the code without a mail would fail only when it is first sent, after its change was committed.
     */
    #[Test]
    public function every_type_of_message_in_the_code_has_a_mail(): void
    {
        self::bootKernel();
        $sent = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../../../src')) as $file) {
            if (!$file instanceof \SplFileInfo || 'php' !== $file->getExtension()) {
                continue;
            }

            \preg_match_all("/new Notification\\([^,]+,\\s*'([a-z_.]+)'/", (string) \file_get_contents($file->getPathname()), $types);
            \array_push($sent, ...$types[1]);
        }

        $sent = \array_values(\array_unique($sent));
        \sort($sent);
        self::assertSame($sent, self::service(MailComposers::class)->types());
    }

    private static function member(): Member
    {
        return MemberFixture::stored(self::service(InMemoryMemberRepository::class), 'jan@example.org', 'Jan Bakker', 'Jan B.');
    }

    private static function setting(string $name, string $value): void
    {
        self::service(InMemorySettingRepository::class)->add(Setting::of($name, $value));
    }

    private static function sentMail(): Email
    {
        self::assertEmailCount(1);
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
