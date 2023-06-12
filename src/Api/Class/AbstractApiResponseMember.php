<?php

namespace Wexample\SymfonyApi\Api\Class;

use Exception;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

abstract class AbstractApiResponseMember
{
    public final const DISPLAY_FORMAT_DEFAULT = 'default';

    public final const DISPLAY_FORMAT_LARGE = 'large';

    public final const DISPLAY_FORMAT_LINE = 'line';

    public final const DISPLAY_FORMAT_MEDIUM = 'medium';

    public final const DISPLAY_FORMAT_PAD = 'pad';

    public final const DISPLAY_FORMAT_SMALL = 'small';

    public final const DISPLAY_FORMAT_FULL = VariableHelper::FULL;

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
