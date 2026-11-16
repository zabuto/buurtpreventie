<?php

declare(strict_types=1);

namespace App\Tests\Identity\UseCase\SetPassword;

use App\Identity\UseCase\SetPassword\SetPassword;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\Component\Validator\Validation;

/**
 * Length is what makes a password hard to guess; there are no rules on kinds of characters, which mainly lead to
 * passwords such as Welkom01! (NIST SP 800-63B: at least 15 characters for a password that is the only factor).
 */
final class SetPasswordTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function passwords(): iterable
    {
        yield '14 characters' => [\str_repeat('a', 14), false];
        yield '15 characters' => [\str_repeat('a', 15), true];
        yield '15 characters with an accent counted once each' => [\str_repeat('é', 15), true];
        yield '4,096 characters' => [\str_repeat('a', 4096), true];
        yield '4,097 characters' => [\str_repeat('a', 4097), false];
        yield '2,049 accented characters, 4,098 bytes' => [\str_repeat('é', 2049), false];
    }

    #[DataProvider('passwords')]
    #[Test]
    public function it_takes_a_password_of_at_least_15_characters_of_any_kind_within_the_4096_bytes_a_hasher_takes(
        #[SensitiveParameter]
        string $password,
        bool $valid,
    ): void {
        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate(new SetPassword($password));

        $this->assertSame($valid, 0 === \count($violations));
    }
}
