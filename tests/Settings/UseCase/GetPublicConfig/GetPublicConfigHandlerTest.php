<?php

declare(strict_types=1);

namespace App\Tests\Settings\UseCase\GetPublicConfig;

use App\Settings\Domain\Setting;
use App\Settings\UseCase\GetPublicConfig\GetPublicConfig;
use App\Settings\UseCase\GetPublicConfig\GetPublicConfigHandler;
use App\Shared\Domain\Actor;
use App\Shared\Domain\AppUrl;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Role;
use App\Tests\Settings\Fake\FixedStandardTexts;
use App\Tests\Settings\Fake\InMemorySettingRepository;
use App\Tests\Shared\Fake\FixedExecutionContext;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GetPublicConfigHandlerTest extends TestCase
{
    private InMemorySettingRepository $settings;

    #[Override]
    protected function setUp(): void
    {
        $this->settings = new InMemorySettingRepository();
        $this->settings->add(Setting::of('association.name', 'Eastside Watch'));
        $this->settings->add(Setting::of('brand.primary', '#0b4f6c'));
        $this->settings->add(Setting::of('contact.name', 'Anna Hughes'));
        $this->settings->add(Setting::of('contact.phone', '06 1234 5678'));
        $this->settings->add(Setting::of('community.group_url', 'https://signal.group/#CjQKIBYlH4b3Xy6cTz9QJ0p8d2fRk1mWnLsVu5eAtGhOiPqAEhC7'));
        $this->settings->add(Setting::of('text.privacy_url', 'https://example.org/privacy'));
        $this->settings->add(Setting::of('patrol.minimum_walkers', '3'));
    }

    #[Test]
    public function it_gives_name_colours_texts_and_staffing_in_the_language_of_the_association(): void
    {
        $this->settings->add(Setting::of('app.language', 'en'));
        $this->settings->add(Setting::of('text.welcome.en', 'Welcome, walkers.'));

        $config = $this->handler()(new GetPublicConfig());

        $this->assertSame(
            [
                'Eastside Watch',
                'Buurtpreventie',
                ['#0b4f6c', '#c2410c', '#8cc4e8', '#fb923c'],
                'en',
                'Welcome, walkers.',
                'Standard text.emergency.en',
                'https://example.org/privacy',
                null,
                3,
                7,
            ],
            [
                $config->associationName,
                $config->associationShortName,
                [$config->primaryColor, $config->accentColor, $config->primaryDarkColor, $config->accentDarkColor],
                $config->language,
                $config->welcomeText,
                $config->emergencyText,
                $config->privacyUrl,
                $config->accessibilityUrl,
                $config->minimumWalkers,
                $config->maximumWalkers,
            ],
        );
    }

    /**
     * The configuration is public, also to search engines: the contact of the coordinator stays with members.
     */
    #[Test]
    public function it_keeps_the_contact_from_a_visitor_with_the_same_keys(): void
    {
        $config = $this->handler()(new GetPublicConfig());

        $this->assertSame(['name' => null, 'email' => null, 'phone' => null], $config->contact);
    }

    /**
     * Whoever holds the invitation can join the group and see the phone number of every member in it.
     */
    #[Test]
    public function it_keeps_the_group_from_a_visitor_with_the_same_keys(): void
    {
        $config = $this->handler()(new GetPublicConfig());

        $this->assertSame(['url' => null, 'service' => null], $config->group);
    }

    #[Test]
    public function it_gives_a_member_the_group_with_its_service(): void
    {
        $config = $this->handler(new Actor(Uuid::v7(), [Role::Walker]))(new GetPublicConfig());

        $this->assertSame(['url' => 'https://signal.group/#CjQKIBYlH4b3Xy6cTz9QJ0p8d2fRk1mWnLsVu5eAtGhOiPqAEhC7', 'service' => 'signal'], $config->group);
    }

    #[Test]
    public function it_gives_a_member_null_for_the_group_of_an_association_without_one(): void
    {
        $this->settings->remove(Setting::of('community.group_url', 'https://signal.group/#CjQKIBYlH4b3Xy6cTz9QJ0p8d2fRk1mWnLsVu5eAtGhOiPqAEhC7'));

        $config = $this->handler(new Actor(Uuid::v7(), [Role::Walker]))(new GetPublicConfig());

        $this->assertSame(['url' => null, 'service' => null], $config->group);
    }

    #[Test]
    public function it_gives_a_member_the_contact(): void
    {
        $config = $this->handler(new Actor(Uuid::v7(), [Role::Walker]))(new GetPublicConfig());

        $this->assertSame(['name' => 'Anna Hughes', 'email' => null, 'phone' => '06 1234 5678'], $config->contact);
    }

    #[Test]
    public function it_gives_the_default_icon_and_no_logo_under_the_path_of_the_base_url_until_they_are_uploaded(): void
    {
        $config = $this->handler()(new GetPublicConfig());

        $this->assertSame(
            [
                null,
                [
                    ['size' => 512, 'url' => '/buurtpreventie/api/v1/media/brand/icon-512-defa017000000001.png'],
                    ['size' => 192, 'url' => '/buurtpreventie/api/v1/media/brand/icon-192-defa017000000001.png'],
                    ['size' => 180, 'url' => '/buurtpreventie/api/v1/media/brand/icon-180-defa017000000001.png'],
                    ['size' => 32, 'url' => '/buurtpreventie/api/v1/media/brand/icon-32-defa017000000001.png'],
                ],
            ],
            [$config->logoUrl, $config->icons],
        );
    }

    #[Test]
    public function it_gives_the_uploaded_logo_and_icon(): void
    {
        $this->settings->add(Setting::of('brand.logo', '0123456789abcdef'));
        $this->settings->add(Setting::of('brand.icon', 'fedcba9876543210'));

        $config = $this->handler()(new GetPublicConfig());

        $this->assertSame(
            ['/buurtpreventie/api/v1/media/brand/logo-512-0123456789abcdef.png', '/buurtpreventie/api/v1/media/brand/icon-192-fedcba9876543210.png'],
            [$config->logoUrl, $config->icons[1]['url']],
        );
    }

    private function handler(?Actor $actor = null): GetPublicConfigHandler
    {
        return new GetPublicConfigHandler(
            $this->settings,
            new FixedStandardTexts(),
            new FixedExecutionContext($actor, Channel::Web),
            AppUrl::fromString('https://example.org/buurtpreventie/'),
        );
    }
}
