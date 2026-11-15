<?php

declare(strict_types=1);

namespace App\Identity\UseCase\RevokeMemberApiTokens;

use App\Identity\Domain\ApiTokenRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Revokes every token of a member at once, such as when the member is deactivated or erased.
 */
#[WriteAction(authorization: 'api_token.revoke_all', audit: 'api_token.revoked')]
final readonly class RevokeMemberApiTokensHandler
{
    public function __construct(
        private ApiTokenRepository $tokens,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeMemberApiTokens $input): void
    {
        $memberId = Uuid::fromString($input->memberId);
        $this->authorization->denyUnlessGranted('api_token.revoke_all', $memberId);

        $this->transaction->run(function () use ($memberId): void {
            $now = $this->clock->now();
            foreach ($this->tokens->ofMember($memberId) as $token) {
                if (!$token->revoke($now)) {
                    continue;
                }

                $this->auditTrail->record(new AuditedAction('api_token.revoked', 'api_token', $token->id()));
            }
        });
    }
}
