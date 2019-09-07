<?php

namespace App\Filter;

use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * SoftDeleteFilter
 */
class SoftDeleteFilter extends SQLFilter
{
    /**
     * @param  ClassMetaData $targetEntity
     * @param  string        $targetTableAlias
     * @return string
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
        if (false === $targetEntity->hasField('deletedAt')) {
            return '';
        }

        $expr = new Expr();

        return $expr->isNull(sprintf('%s.%s', $targetTableAlias, 'deleted_at'));
    }
}
