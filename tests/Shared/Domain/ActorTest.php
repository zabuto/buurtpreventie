<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ActorTest extends TestCase
{
    #[Test]
    public function it_has_only_the_roles_it_was_given(): void
    {
        $actor = new Actor(Uuid::v7(), [Role::Walker, Role::Analyst]);

        $this->assertSame(
            [true, false, true, false],
            [$actor->hasRole(Role::Walker), $actor->hasRole(Role::Coordinator), $actor->hasRole(Role::Analyst), $actor->hasRole(Role::Admin)],
        );
    }
}
