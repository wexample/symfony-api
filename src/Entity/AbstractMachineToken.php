<?php

namespace Wexample\SymfonyApi\Entity;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyApi\Interface\MachineClientInterface;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\HasDateCreatedTrait;

/**
 * One token of a machine client. A client may hold several, so that a new one
 * is handed out before the old one is revoked.
 *
 * The plain token is never stored: only its hash, and a hint naming it in a
 * log. The application's subclass maps the relation to its client entity.
 *
 * Not a mapped superclass, like AbstractEntity above it: Doctrine maps these
 * columns straight into the concrete entity.
 */
abstract class AbstractMachineToken extends AbstractEntity
{
    use HasDateCreatedTrait;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    protected string $tokenHash;

    #[ORM\Column(type: Types::STRING, length: 64)]
    protected string $hint;

    /**
     * Null for a token that does not expire.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $dateExpiration = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $dateRevoked = null;

    /**
     * Refreshed on use, at most once per `machine_token.last_used_interval`.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $dateLastUsed = null;

    public function __construct()
    {
        parent::__construct();

        $this->setDateCreatedNow();
    }

    abstract public function getClient(): MachineClientInterface;

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): self
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getHint(): string
    {
        return $this->hint;
    }

    public function setHint(string $hint): self
    {
        $this->hint = $hint;

        return $this;
    }

    public function getDateExpiration(): ?DateTimeImmutable
    {
        return $this->dateExpiration;
    }

    public function setDateExpiration(?DateTimeImmutable $dateExpiration): self
    {
        $this->dateExpiration = $dateExpiration;

        return $this;
    }

    public function getDateRevoked(): ?DateTimeImmutable
    {
        return $this->dateRevoked;
    }

    public function revoke(DateTimeImmutable $now = new DateTimeImmutable()): self
    {
        $this->dateRevoked ??= $now;

        return $this;
    }

    public function getDateLastUsed(): ?DateTimeImmutable
    {
        return $this->dateLastUsed;
    }

    public function setDateLastUsed(?DateTimeImmutable $dateLastUsed): self
    {
        $this->dateLastUsed = $dateLastUsed;

        return $this;
    }

    public function isUsable(DateTimeInterface $now = new DateTimeImmutable()): bool
    {
        if (null !== $this->dateRevoked && $this->dateRevoked <= $now) {
            return false;
        }

        return null === $this->dateExpiration || $this->dateExpiration > $now;
    }
}
