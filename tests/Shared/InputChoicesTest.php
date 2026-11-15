<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Identity\Domain\TokenScope;
use App\Identity\UseCase\CreateApiToken\CreateApiToken;
use App\Membership\UseCase\AddMember\AddMember;
use App\Membership\UseCase\ChangeMemberRoles\ChangeMemberRoles;
use App\Patrol\Domain\Catalogue\OutcomeKind;
use App\Patrol\Domain\Round\RoundStatus;
use App\Patrol\UseCase\CreateOutcome\CreateOutcome;
use App\Patrol\UseCase\ListRounds\ListRounds;
use App\Shared\Domain\Role;
use BackedEnum;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * An input that takes the value of a domain enum accepts exactly the values of that enum, so a case added to the
 * enum is accepted without touching the input.
 */
final class InputChoicesTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(string): object, class-string<BackedEnum>}>
     */
    public static function inputs(): iterable
    {
        yield 'AddMember roles' => [
            static fn(string $value): object => new AddMember('jan@example.org', 'Jan Bakker', 'Jan B.', roles: [$value]),
            Role::class,
        ];
        yield 'ChangeMemberRoles roles' => [
            static fn(string $value): object => new ChangeMemberRoles('0193a3c0-0000-7000-8000-000000000001', [$value]),
            Role::class,
        ];
        yield 'CreateApiToken scopes' => [
            static fn(string $value): object => new CreateApiToken('0193a3c0-0000-7000-8000-000000000001', 'Script', [$value]),
            TokenScope::class,
        ];
        yield 'CreateOutcome kind' => [
            static fn(string $value): object => new CreateOutcome('Niets te melden', $value),
            OutcomeKind::class,
        ];
        yield 'ListRounds status' => [
            static fn(string $value): object => new ListRounds('2026-11-14T00:00:00+01:00', '2026-11-17T00:00:00+01:00', $value),
            RoundStatus::class,
        ];
    }

    /**
     * @param Closure(string): object $input
     * @param class-string<BackedEnum> $enum
     */
    #[DataProvider('inputs')]
    #[Test]
    public function it_accepts_exactly_the_values_of_the_enum(Closure $input, string $enum): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $values = \array_map(static fn(BackedEnum $case): string => (string) $case->value, $enum::cases());

        $accepted = [];
        foreach ([...$values, 'unknown'] as $value) {
            if (0 === \count($validator->validate($input($value)))) {
                $accepted[] = $value;
            }
        }

        $this->assertSame($values, $accepted);
    }
}
