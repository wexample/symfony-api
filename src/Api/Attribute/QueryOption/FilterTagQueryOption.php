<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;
use Wexample\SymfonyApi\Helper\ApiHelper;

/**
 * `?filter-tag=late`: one of the named filters a list offers — what the
 * application's query does with it is its own. The names declared in `tags`
 * are the only ones taken; any other is refused with a `400`.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class FilterTagQueryOption extends AbstractQueryOption
{
    public string $key = ApiHelper::_KEBAB_FILTER_TAG;

    /**
     * @param list<string> $tags
     */
    public function __construct(
        public array $tags = [],
        public mixed $default = null,
        bool $required = false,
    ) {
        parent::__construct(
            $required
        );
    }

    public function getConstraint(): Constraint
    {
        return $this->tags ? new Choice(choices: $this->tags) : new Type(Types::STRING);
    }
}
