<?php

declare(strict_types=1);

namespace App\Identity\UseCase\RevokeApiToken;

use App\Identity\Domain\ApiTokenRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

#[WriteAction(authorization: 'api_token.revoke', audit: 'api_token.revoked')]
final readonly class RevokeApiTokenHandler
{
    public function __construct(
        private ApiTokenRepository $tokens,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeApiToken $input): void
    {
        $this->transaction->run(function () use ($input): void {
            $token = $this->tokens->get(Uuid::fromString($input->tokenId));
            $this->authorization->denyUnlessGranted('api_token.revoke', $token);

            if ($token->revoke($this->clock->now())) {
                $this->auditTrail->record(new AuditedAction('api_token.revoked', 'api_token', $token->id()));
            }
        });
    }
}
