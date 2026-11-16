<?php

declare(strict_types=1);

namespace App\Identity\UseCase\RequestLoginCode;

use App\Identity\Domain\LoginChallenge;
use App\Identity\Domain\LoginChallengeRepository;
use App\Identity\Domain\LoginCode;
use App\Identity\Domain\LoginCodeGenerator;
use App\Identity\Domain\LoginCodeHasher;
use App\Shared\Domain\AuditedAction;
use App\Shared\Domain\AuditTrail;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\Notification;
use App\Shared\Domain\Notifier;
use App\Shared\Domain\Transaction;
use App\Shared\Domain\WriteAction;
use Psr\Clock\ClockInterface;

/**
 * Mails an active member a login code and ends the member's earlier requests. An unknown address or inactive member
 * gets a challenge too, which no code opens, so the answer never tells whether an address belongs to a member.
 */
#[WriteAction(authorization: 'account.request_login_code', audit: 'account.login_code_requested')]
final readonly class RequestLoginCodeHandler
{
    public function __construct(
        private MemberDirectory $members,
        private LoginChallengeRepository $challenges,
        private LoginCodeGenerator $codeGenerator,
        private LoginCodeHasher $codeHasher,
        private Notifier $notifier,
        private Authorization $authorization,
        private AuditTrail $auditTrail,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RequestLoginCode $input): RequestLoginCodeOutput
    {
        $this->authorization->denyUnlessGranted('account.request_login_code');

        $identity = $this->members->identify(EmailAddress::fromString($input->email));
        $memberId = true === $identity?->active ? $identity->memberId : null;
        $challengeId = $this->codeGenerator->newChallengeId();
        $code = $this->codeGenerator->newCode();
        $now = $this->clock->now();
        $challenge = LoginChallenge::open($challengeId, $memberId, $this->codeHasher->hash($challengeId, $code), $now);

        $this->transaction->run(function () use ($challenge, $code, $now): void {
            $memberId = $challenge->memberId();
            if (null !== $memberId) {
                foreach ($this->challenges->openOfMember($memberId, $now) as $earlier) {
                    $earlier->end($now);
                }
            }

            $this->challenges->add($challenge);
            if (null === $memberId) {
                return;
            }

            $this->auditTrail->record(new AuditedAction('account.login_code_requested', 'member', $memberId));
            $this->notifier->send(new Notification($memberId, 'account.login_code', [
                'code' => LoginCode::shown($code),
                'minutes' => LoginChallenge::VALIDITY_IN_MINUTES,
            ]));
        });

        return new RequestLoginCodeOutput($challengeId);
    }
}
