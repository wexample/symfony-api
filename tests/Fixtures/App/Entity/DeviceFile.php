<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Entity;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * A file a device holds, computed on demand and never stored: an entity for
 * the API — normalizer, TypeScript class, front collection — with no table.
 *
 * Its id derives from what identifies it, so the same file keeps the same id
 * from one request to the next.
 */
class DeviceFile extends AbstractEntity
{
    private const string ID_NAMESPACE = '6f2c1a40-5a8e-4b8a-9d61-0f4e3c2b1a00';

    public function __construct(
        public readonly string $name,
        public readonly int $size,
        public readonly ?DateTimeImmutable $dateModified = null,
    ) {
        $this->id = Uuid::v5(Uuid::fromString(self::ID_NAMESPACE), $name);
    }
}
