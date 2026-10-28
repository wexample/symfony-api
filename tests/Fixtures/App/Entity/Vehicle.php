<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * The root of a hierarchy: what every vehicle has, each kind in a table of
 * its own. No discriminator map, the subclasses being the test's to add — as
 * a package's root cannot list the subclasses other packages give it.
 */
#[ORM\Entity]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'kind', type: Types::STRING)]
abstract class Vehicle extends AbstractEntity
{
    #[ORM\Column(type: Types::STRING, length: 32)]
    public string $name;
}
