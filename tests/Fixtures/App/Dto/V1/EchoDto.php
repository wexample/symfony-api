<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Dto\V1;

use Wexample\SymfonyApi\Api\Attribute\RequiredDtoProperty;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;

class EchoDto extends AbstractDto
{
    #[RequiredDtoProperty]
    public int $value;
}
