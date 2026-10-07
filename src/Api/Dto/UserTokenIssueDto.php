<?php

namespace Wexample\SymfonyApi\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a person gives when they ask for a token of their own.
 */
class UserTokenIssueDto extends AbstractDto
{
    /**
     * Telling their tokens apart: "import script", "laptop".
     */
    #[Assert\Length(max: 255)]
    public ?string $label = null;

    /**
     * The day it stops working, `Y-m-d`; none for a token that does not
     * expire.
     */
    #[Assert\Date]
    public ?string $expiresAt = null;

    /**
     * The roles of the person the token is limited to; none given for all.
     *
     * @var list<string>|null
     */
    #[Assert\All([new Assert\Type('string')])]
    public ?array $scopes = null;
}
