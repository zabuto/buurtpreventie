<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\CodeAttempt;
use App\Identity\Domain\LoginChallengeRepository;
use App\Identity\Domain\LoginCode;
use App\Identity\Domain\LoginCodeHasher;
use App\Shared\Domain\MemberDirectory;
use App\Shared\Domain\Transaction;
use App\Shared\Infrastructure\Security\MemberUser;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * Checks a typed login code against its challenge, run by the passport of LoginCodeAuthenticator after the login
 * throttling. The attempt is stored before the refusal, so a wrong code counts even though the login fails.
 */
final readonly class LoginCodeCheck
{
    /** The format LoginChallenge::open() takes; anything else is unknown without asking the database. */
    private const string CHALLENGE_FORMAT = '/^[0-9a-f]{32}\z/';

    public function __construct(
        private LoginChallengeRepository $challenges,
        private LoginCodeHasher $codeHasher,
        private MemberDirectory $members,
        private MemberUserProvider $users,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws LoginRefused login_code_wrong with the attempts left, or login_code_ended for a challenge that is
     *                      unknown, used, expired, out of attempts or of a member who is no longer active
     */
    public function member(string $challengeId, #[SensitiveParameter] string $typedCode): MemberUser
    {
        if (1 !== \preg_match(self::CHALLENGE_FORMAT, $challengeId)) {
            throw new LoginRefused('unknown_challenge', problem: 'login_code_ended');
        }

        $hash = $this->codeHasher->hash($challengeId, LoginCode::typed($typedCode));
        [$attempt, $memberId, $attemptsLeft] = $this->transaction->run(function () use ($challengeId, $hash): array {
            $challenge = $this->challenges->forUpdate($challengeId);
            if (null === $challenge) {
                return [CodeAttempt::Ended, null, 0];
            }

            return [$challenge->attempt($hash, $this->clock->now()), $challenge->memberId(), $challenge->attemptsLeft()];
        });

        $identity = null === $memberId ? null : $this->members->identity($memberId);

        return match (true) {
            CodeAttempt::Wrong === $attempt => throw new LoginRefused('wrong_code', $memberId, 'login_code_wrong', $attemptsLeft),
            CodeAttempt::Ended === $attempt => throw new LoginRefused(null === $memberId ? 'unknown_challenge' : 'code_ended', $memberId, 'login_code_ended'),
            null === $identity || !$identity->active => throw new LoginRefused('member_inactive', $memberId, 'login_code_ended'),
            default => $this->users->userOf($identity),
        };
    }
}
