<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Dto\Validation;

use Symfony\Component\Validator\Constraints as Assert;
use Wexample\SymfonyApi\Api\Attribute\RequiredDtoProperty;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;

/**
 * A required title, a nested author, and a note left untyped: it takes
 * anything, null among it.
 */
class BookDto extends AbstractDto
{
    #[RequiredDtoProperty]
    #[Assert\Length(max: 20)]
    public string $title;

    public ?AuthorDto $author = null;

    public $note = null;
}
