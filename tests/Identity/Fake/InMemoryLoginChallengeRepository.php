<?php

declare(strict_types=1);

namespace App\Tests\Identity\Fake;

use App\Identity\Domain\LoginChallenge;
use App\Identity\Domain\LoginChallengeRepository;
use DateTimeImmutable;
use Override;
use Symfony\Component\Uid\Uuid;

final class InMemoryLoginChallengeRepository implements LoginChallengeRepository
{
    /** @var array<string, LoginChallenge> */
    private array $challenges = [];

    #[Override]
    public function add(LoginChallenge $challenge): void
    {
        $this->challenges[$challenge->id()] = $challenge;
    }

    #[Override]
    public function forUpdate(string $id): ?LoginChallenge
    {
        return $this->challenges[$id] ?? null;
    }

    #[Override]
    public function openOfMember(Uuid $memberId, DateTimeImmutable $now): array
    {
        return \array_values(\array_filter(
            $this->challenges,
            static fn(LoginChallenge $challenge): bool => true === $challenge->memberId()?->equals($memberId) && $challenge->isOpenAt($now),
        ));
    }

    /**
     * @return list<LoginChallenge>
     */
    public function all(): array
    {
        return \array_values($this->challenges);
    }
}
