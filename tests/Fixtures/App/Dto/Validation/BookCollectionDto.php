<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Dto\Validation;

use Wexample\SymfonyApi\Api\Dto\AbstractCollectionDto;

class BookCollectionDto extends AbstractCollectionDto
{
    /**
     * @var list<BookDto>
     */
    public array $items = [];

    public static function getCollectionItemDtoClass(): string
    {
        return BookDto::class;
    }
}
