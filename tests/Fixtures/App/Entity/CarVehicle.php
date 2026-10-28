<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A kind of vehicle with a normalizer of its own: named after the root, so
 * that its discriminator is the word left — `car`.
 */
#[ORM\Entity]
class CarVehicle extends Vehicle
{
    #[ORM\Column(type: Types::INTEGER)]
    public int $seats = 4;
}
