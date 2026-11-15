<?php

declare(strict_types=1);

namespace App\Tests\Audit\Domain;

use App\Audit\Domain\AuditPolicy;
use App\Shared\Domain\Actor;
use App\Shared\Domain\Role;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AuditPolicyTest extends TestCase
{
    /**
     * Part of erasing a member, which only an admin does.
     */
    #[Test]
    public function it_lets_only_an_admin_erase_the_name_of_an_actor(): void
    {
        $policy = new AuditPolicy();

        $this->assertSame(
            [true, false],
            [
                $policy->mayEraseActorName(new Actor(Uuid::v7(), [Role::Admin])),
                $policy->mayEraseActorName(new Actor(Uuid::v7(), [Role::Coordinator, Role::Walker])),
            ],
        );
    }
}
