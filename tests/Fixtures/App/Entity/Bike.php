<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A kind of vehicle with no normalizer of its own, and a name that does not
 * end with the root's: the root's normalizer answers for it, and its
 * discriminator is its whole name.
 */
#[ORM\Entity]
class Bike extends Vehicle
{
    #[ORM\Column(type: Types::INTEGER)]
    public int $gears = 1;
}
