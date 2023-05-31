<?php

namespace Wexample\SymfonyApi\Helper;

use Wexample\SymfonyHelpers\Helper\VariableHelper;

class ApiHelper
{
    public final const _KEBAB_DISPLAY_FORMAT = 'display-format';
    public final const KEY_RESPONSE_DATA = VariableHelper::DATA;
    public final const _KEBAB_FILTER_TAG = 'filter-tag';
    public final const DISPLAY_FORMAT = 'display_format';
    public final const FILTER_TAG = 'filter_tag';
    public final const KEY_RESPONSE_MESSAGE = VariableHelper::MESSAGE;
    public final const KEY_RESPONSE_STATUS = VariableHelper::STATUS;
    public final const KEY_RESPONSE_TYPE = VariableHelper::TYPE;
    public final const RESPONSE_TYPE_FAILURE = VariableHelper::ERROR;
    public final const RESPONSE_TYPE_SUCCESS = VariableHelper::SUCCESS;
}