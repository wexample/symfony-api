<?php

namespace Wexample\SymfonyApi\Api\Class;

use Exception;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

abstract class AbstractApiResponseMember
{
    final public const DISPLAY_FORMAT_DEFAULT = 'default';

    final public const DISPLAY_FORMAT_LARGE = 'large';

    final public const DISPLAY_FORMAT_LINE = 'line';

    final public const DISPLAY_FORMAT_MEDIUM = 'medium';

    final public const DISPLAY_FORMAT_PAD = 'pad';

    final public const DISPLAY_FORMAT_SMALL = 'small';

    final public const DISPLAY_FORMAT_FULL = VariableHelper::FULL;

    public static function getDisplayFormats(): array
    {
        return [
            self::DISPLAY_FORMAT_DEFAULT,
            self::DISPLAY_FORMAT_LARGE,
            self::DISPLAY_FORMAT_LINE,
            self::DISPLAY_FORMAT_MEDIUM,
            self::DISPLAY_FORMAT_PAD,
            self::DISPLAY_FORMAT_SMALL,
            self::DISPLAY_FORMAT_FULL,
        ];
    }

    /**
     * @throws Exception
     */
    public function __construct(
        public mixed $data,
        public string $displayFormat
    ) {
    }
}
