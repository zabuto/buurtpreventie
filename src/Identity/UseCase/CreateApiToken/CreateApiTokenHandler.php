<?php

declare(strict_types=1);

namespace App\Identity\UseCase\CreateApiToken;

use App\Identity\Domain\ApiToken;
use App\Identity\Domain\ApiTokenRepository;
use App\Identity\Domain\SecretGenerator;
use App\Identity\Domain\TokenScope;
use App\Identity\Domain\TokenTerms;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'api_token.create', audit: 'api_token.created')]
final readonly class CreateApiTokenHandler
{
    public function __construct(
        private ApiTokenRepository $tokens,
        private SecretGenerator $secretGenerator,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateApiToken $input): CreateApiTokenOutput
    {
        $memberId = Uuid::fromString($input->memberId);
        $this->authorization->denyUnlessGranted('api_token.create', $memberId);

        $secret = $this->secretGenerator->newSecret();
        $token = ApiToken::issue(
            $memberId,
            new TokenTerms(
                $input->name,
                \array_map(TokenScope::from(...), $input->scopes),
                null === $input->expiresAt ? null : new DateTimeImmutable($input->expiresAt),
            ),
            $secret,
            $this->clock->now(),
        );

        $this->transaction->run(function () use ($token): void {
            $this->tokens->add($token);
            $this->auditTrail->record(new AuditedAction('api_token.created', 'api_token', $token->id(), [
                'name' => ['old' => null, 'new' => $token->name()],
                'scopes' => ['old' => null, 'new' => \implode(',', \array_map(static fn(TokenScope $scope): string => $scope->value, $token->scopes()))],
            ]));
        });

        return new CreateApiTokenOutput($token->id()->toRfc4122(), $secret);
    }
}
