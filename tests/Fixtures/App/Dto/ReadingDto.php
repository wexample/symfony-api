<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Wexample\SymfonyApi\Api\Attribute\RequiredDtoProperty;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;

class ReadingDto extends AbstractDto
{
    final public const float VALUE_THAT_FAILS = 66.6;

    #[RequiredDtoProperty]
    #[Assert\Range(min: 0, max: 100)]
    public float $value;

    public ?string $code = null;
}
