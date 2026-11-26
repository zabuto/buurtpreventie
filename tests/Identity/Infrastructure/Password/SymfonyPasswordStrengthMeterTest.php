<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Password;

use App\Identity\Infrastructure\Password\SymfonyPasswordStrengthMeter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

final class SymfonyPasswordStrengthMeterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function passwords(): iterable
    {
        yield 'a passphrase of three short words' => ['fiets lamp regen', true];
        yield 'one letter repeated' => ['aaaaaaaaaaaaaaa', false];
        yield 'a word with digits' => ['Welkom01!Welkom', false];
    }

    #[DataProvider('passwords')]
    #[Test]
    public function it_finds_a_password_strong_enough_from_medium_up(#[SensitiveParameter] string $password, bool $strongEnough): void
    {
        $this->assertSame($strongEnough, new SymfonyPasswordStrengthMeter()->isStrongEnough($password));
    }
}
