<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Dto;

/**
 * One row of the readings list, as the response gives it.
 */
class ReadingRowDto
{
    public float $value;

    public ?string $code = null;
}
