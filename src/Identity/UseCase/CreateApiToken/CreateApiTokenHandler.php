<?php

declare(strict_types=1);

namespace App\Identity\UseCase\CreateApiToken;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\ApiTokenRepository;
use App\Identity\Domain\ApiTokensDisabled;
use App\Identity\Domain\InvalidTokenExpiry;
use App\Identity\Domain\SecretGenerator;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Settings\UseCase\GetApiTokenAccess\GetApiTokenAccess;
use App\Settings\UseCase\GetApiTokenAccess\GetApiTokenAccessHandler;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\ReauthenticationRequired;
use App\Shared\Domain\RecentAuthentication;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Creates a personal API token. It takes a recent login, which an API token never is, so a leaked token or a session
 * that only stayed logged in cannot mint a token that outlives it.
 */
#[WriteAction(authorization: 'api_token.create', audit: 'api_token.created')]
final readonly class CreateApiTokenHandler
{
    public function __construct(
        private ApiTokenRepository $tokens,
        private SecretGenerator $secretGenerator,
        private Authorization $authorization,
        private ExecutionContext $executionContext,
        private RecentAuthentication $recentAuthentication,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
        private GetApiTokenAccessHandler $access,
    ) {
    }

    /**
     * @throws AccessDenied for another member, or nobody acting
     * @throws ApiTokensDisabled while the association has API tokens switched off
     * @throws ReauthenticationRequired without a recent login
     * @throws InvalidTokenExpiry for an expiry not within 365 days
     */
    public function __invoke(CreateApiToken $input): CreateApiTokenOutput
    {
        $memberId = null === $input->memberId ? $this->executionContext->actor()?->memberId : Uuid::fromString($input->memberId);
        if (null === $memberId) {
            throw AccessDenied::forAction('api_token.create');
        }

        $this->authorization->denyUnlessGranted('api_token.create', $memberId);
        if (!($this->access)(new GetApiTokenAccess())->allowed) {
            throw ApiTokensDisabled::create();
        }

        if (!$this->recentAuthentication->isRecent()) {
            throw ReauthenticationRequired::forAction('api_token.create');
        }

        $secret = $this->secretGenerator->newSecret();
        $token = ApiToken::issue(
            $memberId,
            new TokenTerms(
                $input->name,
                \array_map(TokenScope::from(...), $input->scopes),
                new DateTimeImmutable($input->expiresAt),
            ),
            $secret,
            $this->clock->now(),
        );

        $this->transaction->run(function () use ($token, $input): void {
            $this->tokens->add($token);
            $this->auditTrail->record(new AuditedAction('api_token.created', 'api_token', $token->id(), [
                'name' => ['old' => null, 'new' => $token->name()],
                'scopes' => ['old' => null, 'new' => \implode(',', \array_map(static fn(TokenScope $scope): string => $scope->value, $token->scopes()))],
                'expiresAt' => ['old' => null, 'new' => $input->expiresAt],
            ]));
        });

        return new CreateApiTokenOutput($token->id()->toRfc4122(), $secret);
    }
}
