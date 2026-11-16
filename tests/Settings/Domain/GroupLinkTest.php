<?php

declare(strict_types=1);

namespace App\Tests\Settings\Domain;

use App\Settings\Domain\GroupLink;
use App\Settings\Domain\GroupService;
use App\Settings\Domain\InvalidGroupLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GroupLinkTest extends TestCase
{
    private const string WHATSAPP = 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv';
    private const string SIGNAL = 'https://signal.group/#CjQKIBYlH4b3Xy6cTz9QJ0p8d2fRk1mWnLsVu5eAtGhOiPqAEhC7';

    /**
     * @return iterable<string, array{string, GroupService}>
     */
    public static function invitations(): iterable
    {
        yield 'WhatsApp' => [self::WHATSAPP, GroupService::WhatsApp];
        yield 'Signal' => [self::SIGNAL, GroupService::Signal];
    }

    #[Test]
    #[DataProvider('invitations')]
    public function it_takes_an_invitation_link_and_names_its_service(string $url, GroupService $service): void
    {
        $link = GroupLink::fromString('  ' . $url . "\n");

        $this->assertSame([$url, $service], [$link->url, $link->service]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'WhatsApp over http' => ['http://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv'];
        yield 'Signal over http' => ['http://signal.group/#CjQKIBYlH4b3Xy6cTz9QJ0p8d2fRk1mWnLsVu5eAtGhOiPqAEhC7'];
        yield 'another host' => ['https://example.org/AbCdEfGhIjKlMnOpQrStUv'];
        yield 'a host that only starts like WhatsApp' => ['https://chat.whatsapp.com.example.org/AbCdEfGhIjKlMnOpQrStUv'];
        yield 'WhatsApp as user name of another host' => ['https://chat.whatsapp.com@example.org/AbCdEfGhIjKlMnOpQrStUv'];
        yield 'WhatsApp without a code' => ['https://chat.whatsapp.com/'];
        yield 'WhatsApp with a query' => ['https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv?x=1'];
        yield 'a WhatsApp channel, not a group' => ['https://whatsapp.com/channel/0029VaAbCdEfGhIjKlMnOp'];
        yield 'Signal without the group in the fragment' => ['https://signal.group/'];
        yield 'Signal with a path' => ['https://signal.group/x#CjQKIBYlH4b3Xy6cTz9QJ0p8d2fRk1mWnLsVu5eAtGhOiPqAEhC7'];
        yield 'markup in the link' => ['https://chat.whatsapp.com/AbCd"><script>'];
    }

    #[Test]
    #[DataProvider('refused')]
    public function it_refuses_anything_but_a_group_invitation_of_whatsapp_or_signal(string $url): void
    {
        $this->expectException(InvalidGroupLink::class);

        GroupLink::fromString($url);
    }
}
