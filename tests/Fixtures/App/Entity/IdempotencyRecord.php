<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyApi\Entity\AbstractIdempotencyRecord;

#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['scope', 'idempotency_key'])]
class IdempotencyRecord extends AbstractIdempotencyRecord
{
}
