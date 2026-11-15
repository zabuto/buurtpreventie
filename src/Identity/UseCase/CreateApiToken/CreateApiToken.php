<?php

declare(strict_types=1);

namespace App\Identity\UseCase\CreateApiToken;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateApiToken
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        #[Assert\Uuid]
        public string $memberId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
        #[Assert\Count(min: 1)]
        #[Assert\All([new Assert\Choice(choices: ['read', 'write', 'admin'])])]
        public array $scopes,
        #[Assert\DateTime(format: \DATE_ATOM)]
        public ?string $expiresAt = null,
    ) {
    }
}
