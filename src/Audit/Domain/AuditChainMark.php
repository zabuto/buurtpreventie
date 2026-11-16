<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * One of the two fixed points of the audit chain: the head, the last entry, which every writer locks; and the anchor,
 * the last pruned entry, from which the chain is checked.
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_chain')]
final class AuditChainMark
{
    public const string HEAD = 'head';
    public const string ANCHOR = 'anchor';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 10)]
        private readonly string $name,
        #[ORM\Column(type: 'bigint')]
        private int $chainPosition,
        #[ORM\Column(type: 'binary', length: 32, options: ['fixed' => true])]
        private string $chainHash,
    ) {
    }

    /**
     * @param self::HEAD|self::ANCHOR $name
     */
    public static function startOf(string $name): self
    {
        $start = ChainLink::start();

        return new self($name, $start->position, $start->hash);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function link(): ChainLink
    {
        return new ChainLink($this->chainPosition, $this->chainHash);
    }

    public function moveTo(ChainLink $link): void
    {
        $this->chainPosition = $link->position;
        $this->chainHash = $link->hash;
    }
}
