<?php

declare(strict_types=1);

namespace App\Identity\UseCase\ListApiTokens;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\ApiTokenRepository;
use App\Identity\Domain\TokenScope;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\ReadAction;
use DateTimeImmutable;
use SensitiveParameter;

/**
 * Lists the tokens the member acting has not revoked, newest first; an expired one stays, so the member sees why a
 * script stopped.
 */
#[ReadAction]
final readonly class ListApiTokensHandler
{
    public function __construct(
        private ApiTokenRepository $tokens,
        private ExecutionContext $executionContext,
    ) {
    }

    /**
     * @throws AccessDenied when nobody acts
     */
    public function __invoke(ListApiTokens $input): ListApiTokensOutput
    {
        $memberId = $this->executionContext->actor()->memberId ?? throw AccessDenied::forAction('api_token.list');

        $tokens = \array_values(\array_filter(
            $this->tokens->ofMember($memberId),
            static fn(#[SensitiveParameter] ApiToken $token): bool => null === $token->revokedAt(),
        ));
        \usort($tokens, static fn(ApiToken $a, ApiToken $b): int => $b->createdAt() <=> $a->createdAt());

        return new ListApiTokensOutput(\array_map(self::summary(...), $tokens));
    }

    /**
     * @return array{id: string, name: string, scopes: list<string>, createdAt: string, expiresAt: string, lastUsedAt: string|null}
     */
    private static function summary(#[SensitiveParameter] ApiToken $token): array
    {
        return [
            'id' => $token->id()->toRfc4122(),
            'name' => $token->name(),
            'scopes' => \array_map(static fn(TokenScope $scope): string => $scope->value, $token->scopes()),
            'createdAt' => self::local($token->createdAt()),
            'expiresAt' => LocalTime::of($token->expiresAt())->format(\DATE_ATOM),
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
