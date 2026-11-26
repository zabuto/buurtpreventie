<?php

declare(strict_types=1);

namespace App\Identity\UseCase\ListAllApiTokens;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\ApiTokenRepository;
use App\Identity\Domain\TokenScope;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\MemberNames;
use App\Shared\Domain\ReadAction;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * Lists every usable token of every member, newest first, so an admin spots one that does not belong and revokes the
 * tokens of its member.
 */
#[ReadAction]
final readonly class ListAllApiTokensHandler
{
    public function __construct(
        private ApiTokenRepository $tokens,
        private MemberNames $memberNames,
        private Authorization $authorization,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws AccessDenied for anyone but an admin
     */
    public function __invoke(ListAllApiTokens $input): ListAllApiTokensOutput
    {
        $this->authorization->denyUnlessGranted('api_token.list_all');

        $tokens = $this->tokens->usableAt($this->clock->now());
        \usort($tokens, static fn(ApiToken $a, ApiToken $b): int => $b->createdAt() <=> $a->createdAt());

        return new ListAllApiTokensOutput(\array_map($this->summary(...), $tokens));
    }

    /**
     * @return array{id: string, memberId: string, member: string|null, name: string, scopes: list<string>, createdAt: string, expiresAt: string, lastUsedAt: string|null}
     */
    private function summary(#[SensitiveParameter] ApiToken $token): array
    {
        return [
            'id' => $token->id()->toRfc4122(),
            'memberId' => $token->memberId()->toRfc4122(),
            'member' => $this->memberNames->displayNameOf($token->memberId()),
            'name' => $token->name(),
            'scopes' => \array_map(static fn(TokenScope $scope): string => $scope->value, $token->scopes()),
            'createdAt' => self::local($token->createdAt()),
            'expiresAt' => self::local($token->expiresAt()),
            'lastUsedAt' => self::local($token->lastUsedAt()),
        ];
    }

    /**
     * @return ($instant is null ? null : string)
     */
    private static function local(?DateTimeImmutable $instant): ?string
    {
        return null === $instant ? null : LocalTime::of($instant)->format(\DATE_ATOM);
    }
}
