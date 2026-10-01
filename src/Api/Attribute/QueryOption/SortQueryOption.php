<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

/**
 * The order of a list: `?sort=lastName,-dateCreated` — sortable names
 * separated by commas, `-` for descending, the first one first.
 *
 * Only the names the route declares are accepted, and only their declared
 * expressions reach the query: what the caller sends never does.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class SortQueryOption extends AbstractQueryOption
{
    final public const string KEY = 'sort';

    final public const string DESCENDING = '-';

    final public const string SEPARATOR = ',';

    public string $key = self::KEY;

    /**
     * @var array<string, ?string> Sortable name => DQL expression, null for
     *     the property of the same name on the root alias.
     */
    public readonly array $allowed;

    /**
     * @param array<int|string, string> $allowed Sortable names, or name =>
     *     DQL expression (`'establishment' => 'establishment.name'`) for what
     *     is not a property of the root entity.
     * @param string|null $default The order when none is asked, same syntax.
     * @param string|null $tieBreaker A unique property of the root entity,
     *     added last so that rows of equal values keep one order across pages.
     * @param bool $emptyLast Rows with nothing to sort on go last whichever
     *     way the list runs — PostgreSQL puts NULL first in a descending
     *     order, and DQL has no NULLS LAST.
     */
    public function __construct(
        array $allowed,
        ?string $default = null,
        public readonly ?string $tieBreaker = 'id',
        bool $required = false,
        public readonly bool $emptyLast = false,
    ) {
        $normalized = [];
        foreach ($allowed as $name => $expression) {
            is_int($name)
                ? $normalized[$expression] = null
                : $normalized[$name] = $expression;
        }

        foreach (array_keys($normalized) as $name) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name)) {
                throw new InvalidArgumentException('Invalid sortable name "' . $name . '".');
            }
        }

        $this->allowed = $normalized;
        $this->default = $default;

        parent::__construct($required);

        if (null !== $default && ! preg_match($this->getPattern(), $default)) {
            throw new InvalidArgumentException('The default order "' . $default . '" uses a name not in the allowed list.');
        }
    }

    public function getConstraint(): Constraint
    {
        // The type first: `?sort[]=…` is an array, which Regex cannot read.
        return new Sequentially([
            new Type('string'),
            new Regex(
                pattern: $this->getPattern(),
                message: 'Sort by ' . implode(', ', array_keys($this->allowed)) . ', separated by commas, "-" before a name for descending.'
            ),
        ]);
    }

    /**
     * @return list<array{name: string, descending: bool}>
     */
    public function parseTerms(?string $value): array
    {
        $terms = [];

        foreach (explode(self::SEPARATOR, (string) ($value ?? $this->default)) as $term) {
            $descending = str_starts_with($term, self::DESCENDING);
            $name = $descending ? substr($term, 1) : $term;

            if (! array_key_exists($name, $this->allowed)) {
                continue;
            }

            $terms[$name] ??= ['name' => $name, 'descending' => $descending];
        }

        return array_values($terms);
    }

    /**
     * Orders the query by the asked terms, replacing any order it had.
     */
    public function apply(
        QueryBuilder $queryBuilder,
        ?string $value,
        ?string $rootAlias = null
    ): QueryBuilder {
        $rootAlias ??= $queryBuilder->getRootAliases()[0];
        $queryBuilder->resetDQLPart('orderBy');
        $expressions = [];

        foreach ($this->parseTerms($value) as $index => $term) {
            $expression = $this->allowed[$term['name']] ?? $rootAlias . '.' . $term['name'];
            $expressions[] = $expression;

            if ($this->emptyLast) {
                $flag = 'sort_empty_' . $index;
                $queryBuilder
                    ->addSelect('CASE WHEN ' . $expression . ' IS NULL THEN 1 ELSE 0 END AS HIDDEN ' . $flag)
                    ->addOrderBy($flag, 'ASC');
            }

            $queryBuilder->addOrderBy($expression, $term['descending'] ? 'DESC' : 'ASC');
        }

        if (null !== $this->tieBreaker && ! in_array($rootAlias . '.' . $this->tieBreaker, $expressions, true)) {
            $queryBuilder->addOrderBy($rootAlias . '.' . $this->tieBreaker, 'ASC');
        }

        return $queryBuilder;
    }

    private function getPattern(): string
    {
        $names = implode('|', array_map(fn (string $name) => preg_quote($name, '/'), array_keys($this->allowed)));
        $term = '-?(?:' . $names . ')';

        return '/^' . $term . '(?:' . preg_quote(self::SEPARATOR, '/') . $term . ')*$/';
    }
}
