<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\GuessablePasswords;
use App\Tests\Identity\Fake\FixedStrengthMeter;
use App\Tests\Identity\Fake\ListedPasswords;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GuessablePasswordsTest extends TestCase
{
    #[Test]
    public function it_takes_a_passphrase_of_unrelated_words(): void
    {
        $guessable = $this->passwords()->isGuessable('fiets lamp regen', ['Mila Jansen', 'Mila J.', 'mila.jansen@example.org']);

        $this->assertFalse($guessable);
    }

    #[Test]
    public function it_refuses_a_listed_password_whatever_its_capitals_and_spaces(): void
    {
        $guessable = $this->passwords(listed: ['ditwachtwoordisgeheim'])->isGuessable('Dit Wachtwoord Is Geheim', []);

        $this->assertTrue($guessable);
    }

    #[Test]
    public function it_refuses_a_password_holding_a_part_of_the_members_name(): void
    {
        $guessable = $this->passwords()->isGuessable('Jansen houdt van fietsen', ['Mila Jansen']);

        $this->assertTrue($guessable);
    }

    #[Test]
    public function it_refuses_a_password_holding_the_whole_name_when_each_part_is_short(): void
    {
        $guessable = $this->passwords()->isGuessable('Mo de Vos fietst graag', ['Mo de Vos']);

        $this->assertTrue($guessable);
    }

    #[Test]
    public function it_refuses_a_password_holding_the_part_of_the_email_address_before_the_at(): void
    {
        $guessable = $this->passwords()->isGuessable('mila.jansen2026 rondje', ['mila.jansen@example.org']);

        $this->assertTrue($guessable);
    }

    #[Test]
    public function it_refuses_a_password_holding_a_part_of_the_email_address_before_the_at(): void
    {
        $guessable = $this->passwords()->isGuessable('rondje jansen in de wijk', ['m_jansen+buurt@example.org']);

        $this->assertTrue($guessable);
    }

    #[Test]
    public function it_ignores_the_domain_of_the_email_address(): void
    {
        $guessable = $this->passwords()->isGuessable('example lamp regen', ['mila@example.org']);

        $this->assertFalse($guessable);
    }

    /**
     * A name part of two or three letters sits in too many words ("Jan" in "januari", "van" in "vanavond") and makes a
     * passphrase no easier to guess.
     */
    #[Test]
    public function it_ignores_a_name_part_shorter_than_four_characters(): void
    {
        $guessable = $this->passwords()->isGuessable('januari vanavond fietsen', ['Jan van Dijk']);

        $this->assertFalse($guessable);
    }

    #[Test]
    public function it_refuses_a_password_the_strength_meter_finds_too_weak(): void
    {
        $guessable = $this->passwords(weak: ['aaaaaaaaaaaaaaa'])->isGuessable('aaaaaaaaaaaaaaa', []);

        $this->assertTrue($guessable);
    }

    /**
     * @param list<string> $listed
     * @param list<string> $weak
     */
    private function passwords(array $listed = [], array $weak = []): GuessablePasswords
    {
        return new GuessablePasswords(new ListedPasswords($listed), new FixedStrengthMeter($weak));
    }
}
