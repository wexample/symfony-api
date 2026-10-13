<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Validator\Constraint;
use Wexample\PhpDate\Helper\DateHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

/**
 * `?year=2025`: a list cut to one year — the current one by default —, on the
 * date field the controller names (`applyQueryOptionYear()`).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class YearQueryOption extends AbstractQueryOption
{
    public string $key = VariableHelper::YEAR;

    public function __construct(
        public mixed $default = null,
        bool $required = false,
    ) {
        $this->default = ! is_null($this->default) ? $this->default : DateHelper::getCurrentYearInt();

        parent::__construct(
            $required
        );
    }

    public function getConstraint(): Constraint
    {
        return $this->buildConstraintType(Types::INTEGER);
    }

    /**
     * The rows whose `$field` falls in the year: from its first instant to the
     * next year's, excluded.
     */
    public static function apply(QueryBuilder $queryBuilder, int $year, string $field): QueryBuilder
    {
        $parameter = 'year_' . preg_replace('/\W/', '_', $field);

        return $queryBuilder
            ->andWhere($field . ' >= :' . $parameter . '_start AND ' . $field . ' < :' . $parameter . '_end')
            ->setParameter($parameter . '_start', new DateTimeImmutable($year . '-01-01 00:00:00'))
            ->setParameter($parameter . '_end', new DateTimeImmutable(($year + 1) . '-01-01 00:00:00'));
    }
}
