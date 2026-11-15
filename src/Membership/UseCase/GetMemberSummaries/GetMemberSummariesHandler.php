<?php

declare(strict_types=1);

namespace App\Membership\UseCase\GetMemberSummaries;

use App\Membership\Domain\Member;
use App\Membership\Domain\MemberRepository;
use App\Shared\Domain\Authorization;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\LookupKey;
use App\Shared\Domain\ReadAction;
use App\Shared\Domain\Role;

/**
 * Lists members by display name. Only a coordinator or admin, or the system itself, sees the full name as well:
 * walkers tell each other apart by display name and need no more.
 */
#[ReadAction]
final readonly class GetMemberSummariesHandler
{
    public function __construct(
        private MemberRepository $members,
        private Authorization $authorization,
        private ExecutionContext $executionContext,
    ) {
    }

    public function __invoke(GetMemberSummaries $input): GetMemberSummariesOutput
    {
        $this->authorization->denyUnlessGranted('member.list');

        $search = \trim($input->search ?? '');
        $actor = $this->executionContext->actor();
        $withFullNames = null === $actor || $actor->hasRole(Role::Coordinator) || $actor->hasRole(Role::Admin);

        return new GetMemberSummariesOutput(\array_map(
            static fn(Member $member): array => self::summary($member, $withFullNames),
            $this->members->search('' === $search ? null : LookupKey::fromName($search)),
        ));
    }

    /**
     * @return array{id: string, displayName: string|null, active: bool, name?: string|null}
     */
    private static function summary(Member $member, bool $withFullName): array
    {
        $summary = ['id' => $member->id()->toRfc4122(), 'displayName' => $member->displayName(), 'active' => $member->isActive()];

        return $withFullName ? [...$summary, 'name' => $member->name()] : $summary;
    }
}
