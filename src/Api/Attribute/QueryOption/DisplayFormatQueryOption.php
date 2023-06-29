<?php

namespace Wexample\SymfonyApi\Api\Attribute\QueryOption;

use Attribute;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Wexample\SymfonyApi\Api\Class\AbstractApiResponseMember;
use Wexample\SymfonyApi\Helper\ApiHelper;

#[\Attribute(\Attribute::TARGET_METHOD)]
class DisplayFormatQueryOption extends AbstractQueryOption
{
    public string $key = ApiHelper::_KEBAB_DISPLAY_FORMAT;

    public function __construct(
        public mixed $default = AbstractApiResponseMember::DISPLAY_FORMAT_DEFAULT,
        bool $required = false,
    ) {
        parent::__construct(
            $required
        );
    }

    public function getConstraint(): Constraint
    {
        return new Choice(AbstractApiResponseMember::getDisplayFormats());
    }
}
