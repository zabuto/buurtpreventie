<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mail;

use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Every mail composer of the app, by the type of message it writes.
 */
final readonly class MailComposers
{
    /** @var array<string, MailComposer> */
    private array $byType;

    /**
     * @param iterable<MailComposer> $composers
     */
    public function __construct(
        #[AutowireIterator(MailComposer::class)]
        iterable $composers,
    ) {
        $byType = [];
        foreach ($composers as $composer) {
            $byType[$composer->type()] = $composer;
        }

        $this->byType = $byType;
    }

    /**
     * @throws LogicException for a type without a mail, a mistake in the code rather than in the message
     */
    public function for(string $type): MailComposer
    {
        return $this->byType[$type] ?? throw new LogicException(\sprintf('No mail for messages of type "%s".', $type));
    }

    /**
     * @return list<string> in order
     */
    public function types(): array
    {
        $types = \array_keys($this->byType);
        \sort($types);

        return $types;
    }
}
