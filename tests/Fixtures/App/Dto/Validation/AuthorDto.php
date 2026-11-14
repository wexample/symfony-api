<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Dto\Validation;

use Wexample\SymfonyApi\Api\Attribute\RequiredDtoProperty;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;

/**
 * Nested in a book.
 */
class AuthorDto extends AbstractDto
{
    #[RequiredDtoProperty]
    public string $name;
}
