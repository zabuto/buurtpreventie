<?php

declare(strict_types=1);

namespace App\Tests\Settings\Infrastructure\Text;

use App\Settings\Domain\SettingName;
use App\Settings\Domain\StandardTexts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class TranslatedStandardTextsTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{SettingName, non-empty-string}>
     */
    public static function texts(): iterable
    {
        yield 'welcome in Dutch' => [SettingName::WelcomeTextNl, 'Welkom bij de buurtpreventie. '];
        yield 'welcome in English' => [SettingName::WelcomeTextEn, 'Welcome to the neighbourhood watch. '];
        yield 'emergency in Dutch' => [SettingName::EmergencyTextNl, "Is er gevaar of gebeurt er nu iets ernstigs? Bel 112.\n\n"];
        yield 'emergency in English' => [SettingName::EmergencyTextEn, "Is there danger, or is something serious happening right now? Call 112.\n\n"];
    }

    /**
     * @param non-empty-string $start
     */
    #[Test]
    #[DataProvider('texts')]
    public function it_gives_the_standard_text_in_the_language_of_the_setting(SettingName $name, string $start): void
    {
        $texts = self::getContainer()->get(StandardTexts::class);
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        $this->assertInstanceOf(StandardTexts::class, $texts);

        $text = $texts->of($name);

        $this->assertStringStartsWith($start, $text);
        $this->assertStringEndsNotWith("\n", $text);
    }
}
