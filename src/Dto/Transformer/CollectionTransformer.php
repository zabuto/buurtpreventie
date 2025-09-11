<?php declare(strict_types=1);

namespace App\Dto\Transformer;

use Doctrine\Common\Collections\Collection;
use LogicException;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * @template T
 * @template T2
 */
final readonly class CollectionTransformer implements TransformCallableInterface
{
    public function __construct(private ObjectMapperInterface $mapper)
    {
    }

    /**
     * @param  Collection<T>|mixed $value
     * @param  object              $source
     * @param  object|null         $target
     * @return array<int, T2>
     * @throws LogicException
     * @throws ReflectionException
     */
    public function __invoke(mixed $value, object $source, ?object $target): array
    {
        if (!$value instanceof Collection || $value->isEmpty()) {
            return [];
        }

        $targetClass = $this->getTargetClass($value->first());
        if (null === $targetClass) {
            throw new LogicException('The collection is not transformable');
        }

        $items = $value->toArray();

        return array_map(fn($element): object => $this->mapper->map($element, $targetClass), $items);
    }

    /**
     * @return class-string<T2>|null
     * @throws ReflectionException
     */
    private function getTargetClass(mixed $element): ?string
    {
        if (!is_object($element)) {
            return null;
        }

        $attribute = (new ReflectionClass($element))->getAttributes(Map::class)[0] ?? null;
        if (null === $attribute) {
            return null;
        }

        /** @var class-string<T2>|null $classname */
        $classname = $attribute->getArguments()['target'] ?? null;

        return $classname;
    }
}
