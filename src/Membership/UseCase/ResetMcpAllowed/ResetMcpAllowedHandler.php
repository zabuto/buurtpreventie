<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ResetMcpAllowed;

use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;

/**
 * Switches MCP off for every member the admin picked, so only the roles the association allows there count again.
 * Each member gets an audit entry of their own, as with a change one by one.
 */
#[WriteAction(authorization: 'member.reset_mcp_allowed', audit: 'member.changed')]
final readonly class ResetMcpAllowedHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ResetMcpAllowed $input): ResetMcpAllowedOutput
    {
        $this->authorization->denyUnlessGranted('member.reset_mcp_allowed');

        return $this->transaction->run(function (): ResetMcpAllowedOutput {
            $reset = 0;
            foreach ($this->members->search() as $member) {
                if (!$member->isMcpAllowed()) {
                    continue;
                }

                $member->allowMcp(false);
                $this->members->update($member);
                $this->auditTrail->record(new AuditedAction('member.changed', 'member', $member->id(), [
                    'mcpAllowed' => ['old' => true, 'new' => false],
                ]));
                ++$reset;
            }

            return new ResetMcpAllowedOutput(membersReset: $reset);
        });
    }
}
