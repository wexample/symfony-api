<?php

namespace Wexample\SymfonyApi\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\HasDateCreatedTrait;

/**
 * One item key already processed for one sender, so that a replay stores
 * nothing twice. Written by BatchReceiverService only, in the transaction of
 * the item it stands for.
 *
 * The application's subclass declares the table and its unique constraint,
 * which the receiver relies on and checks:
 *
 *     #[ORM\Entity]
 *     #[ORM\UniqueConstraint(columns: ['scope', 'idempotency_key'])]
 *     class IdempotencyRecord extends AbstractIdempotencyRecord {}
 */
abstract class AbstractIdempotencyRecord extends AbstractEntity
{
    use HasDateCreatedTrait;

    /**
     * Who sent the item: two senders may use the same key.
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $scope;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $idempotencyKey;

    /**
     * SHA-256 of the item's canonical JSON, telling a replay from a conflict.
     */
    #[ORM\Column(type: Types::STRING, length: 64)]
    protected string $payloadHash;

    /**
     * What the processor returned for the item, given back on a replay.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    protected mixed $result = null;

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getPayloadHash(): string
    {
        return $this->payloadHash;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }
}
