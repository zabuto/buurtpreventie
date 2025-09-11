<?php declare(strict_types=1);

namespace App\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Filter\SQLFilter;

class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (false === $targetEntity->hasField('deletedAt')) {
            return '';
        }

        return (new Expr())->isNull(sprintf('%s.%s', $targetTableAlias, 'deleted_at'));
    }
}
