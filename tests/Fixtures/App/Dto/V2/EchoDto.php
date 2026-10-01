<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Dto\V2;

use Symfony\Component\Validator\Constraints as Assert;
use Wexample\SymfonyApi\Api\Attribute\RequiredDtoProperty;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;

class EchoDto extends AbstractDto
{
    #[RequiredDtoProperty]
    public int $value;

    /**
     * What v2 adds: the unit is now required.
     */
    #[RequiredDtoProperty]
    #[Assert\Choice(['mmHg', 'kPa'])]
    public string $unit;
}
