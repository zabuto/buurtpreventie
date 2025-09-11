<?php declare(strict_types=1);

namespace App\Dto\Transformer;

use App\Dto\RoundDto;
use App\Entity\Round;
use Countable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * @implements TransformCallableInterface<Round, RoundDto>
 */
final readonly class WalkerMinimumTransformer implements TransformCallableInterface
{
    public function __construct(
        #[Autowire(env: 'APPLICATION_WALKER_MINIMUM')]
        private int $minimum,
    )
    {
    }

    public function __invoke(mixed $value, object $source, ?object $target): bool
    {
        $count = (is_numeric($value)) ? $value : 0;
        if ($value instanceof Countable) {
            $count = $value->count();
        }

        return $count >= $this->minimum;
    }
}
