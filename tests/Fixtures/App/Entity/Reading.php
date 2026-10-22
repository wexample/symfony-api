<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * What a device sends in batches.
 */
#[ORM\Entity]
class Reading extends AbstractEntity
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public Device $device;

    #[ORM\Column(type: Types::FLOAT)]
    public float $value;

    /**
     * Unique, so that a test can make a flush fail.
     */
    #[ORM\Column(type: Types::STRING, length: 32, unique: true, nullable: true)]
    public ?string $code = null;
}
