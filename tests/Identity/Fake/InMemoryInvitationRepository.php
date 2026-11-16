<?php

declare(strict_types=1);

namespace App\Tests\Identity\Fake;

use App\Identity\Domain\Invitation;
use App\Identity\Domain\InvitationRepository;
use DateTimeImmutable;
use Override;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final class InMemoryInvitationRepository implements InvitationRepository
{
    /** @var array<string, Invitation> */
    private array $invitations = [];

    #[Override]
    public function add(#[SensitiveParameter] Invitation $invitation): void
    {
        $this->invitations[$invitation->id()->toRfc4122()] = $invitation;
    }

    #[Override]
    public function bySecretHashForUpdate(#[SensitiveParameter] string $secretHash): ?Invitation
    {
        foreach ($this->invitations as $invitation) {
            if (\hash_equals($invitation->secretHash(), $secretHash)) {
                return $invitation;
            }
        }

        return null;
    }

    #[Override]
    public function openOfMember(Uuid $memberId, DateTimeImmutable $now): array
    {
        return \array_values(\array_filter(
            $this->invitations,
            static fn(Invitation $invitation): bool => $invitation->memberId()->equals($memberId) && $invitation->isOpenAt($now),
        ));
    }

    /**
     * @return list<Invitation>
     */
    public function all(): array
    {
        return \array_values($this->invitations);
    }
}
