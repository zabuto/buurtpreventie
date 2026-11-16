<?php

declare(strict_types=1);

namespace App\Identity\UseCase\CreateApiToken;

use App\Identity\Domain\TokenScope;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateApiToken
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
        #[Assert\Count(min: 1)]
        #[Assert\All([new Assert\Choice(callback: [TokenScope::class, 'values'])])]
        public array $scopes,
        /** At most 365 days ahead. */
        #[Assert\NotBlank]
        #[Assert\DateTime(format: \DATE_ATOM)]
        public string $expiresAt,
        /** Empty for the member acting. */
        #[Assert\Uuid]
        public ?string $memberId = null,
    ) {
    }
}
