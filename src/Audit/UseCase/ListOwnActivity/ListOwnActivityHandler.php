<?php

declare(strict_types=1);

namespace App\Audit\UseCase\ListOwnActivity;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditEntryRepository;
use App\Shared\Domain\AccessDenied;
use App\Shared\Domain\ExecutionContext;
use App\Shared\Domain\LocalTime;
use App\Shared\Domain\ReadAction;
use Symfony\Component\Uid\Uuid;

/**
 * What the member acting did, a page at a time. An entry about another member, such as a role an admin gave, keeps
 * the action but not who it was about or what changed: that is the other member's data.
 */
#[ReadAction]
final readonly class ListOwnActivityHandler
{
    public const int PAGE_SIZE = 50;

    public function __construct(
        private AuditEntryRepository $entries,
        private ExecutionContext $executionContext,
    ) {
    }

    /**
     * @throws AccessDenied when nobody acts
     */
    public function __invoke(ListOwnActivity $input): ListOwnActivityOutput
    {
        $memberId = $this->executionContext->actor()->memberId ?? throw AccessDenied::forAction('audit.list_own');

        $page = \max(1, $input->page);
        $entries = $this->entries->byActor($memberId, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE + 1);
        $hasMore = \count($entries) > self::PAGE_SIZE;

        return new ListOwnActivityOutput(
            \array_map(
                static fn(AuditEntry $entry): array => self::line($entry, $memberId),
                \array_slice($entries, 0, self::PAGE_SIZE),
            ),
            $hasMore ? $page + 1 : null,
        );
    }

    /**
     * @return array{occurredAt: string, action: string, subjectType: string, subjectId: string|null, channel: string, changes: array<string, array{old: scalar|null, new: scalar|null}>}
     */
    private static function line(AuditEntry $entry, Uuid $memberId): array
    {
        $aboutAnother = $entry->isAboutAnotherMemberThan($memberId);

        return [
            'occurredAt' => LocalTime::of($entry->occurredAt())->format(\DATE_ATOM),
            'action' => $entry->action(),
            'subjectType' => $entry->subjectType(),
            'subjectId' => $aboutAnother ? null : $entry->subjectId()->toRfc4122(),
            'channel' => $entry->channel()->value,
            'changes' => $aboutAnother ? [] : $entry->changes(),
        ];
    }
}
