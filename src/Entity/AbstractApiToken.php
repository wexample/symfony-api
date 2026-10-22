<?php

namespace Wexample\SymfonyApi\Entity;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\HasDateCreatedTrait;

/**
 * A bearer token of the API, whoever holds it: a machine client
 * (AbstractMachineToken) or a person (AbstractUserToken). Its holder may have
 * several, so that a new one is handed out before the old one is revoked.
 *
 * The plain token is never stored: only its hash, and a hint naming it in a
 * log. The application's subclass maps the relation to its holder entity.
 *
 * Not a mapped superclass, like AbstractEntity above it: Doctrine maps these
 * columns straight into the concrete entity.
 */
abstract class AbstractApiToken extends AbstractEntity
{
    use HasDateCreatedTrait;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    protected string $tokenHash;

    #[ORM\Column(type: Types::STRING, length: 64)]
    protected string $hint;

    /**
     * Free text telling the tokens of one holder apart: "commissioning", "import script".
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $label = null;

    /**
     * Null for a token that does not expire.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $dateExpiration = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $dateRevoked = null;

    /**
     * Refreshed on use, at most once per `last_used_interval` of its kind.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $dateLastUsed = null;

    public function __construct()
    {
        parent::__construct();

        $this->setDateCreatedNow();
    }

    /**
     * Whom the token authenticates. Set by the subclass, whose setter takes
     * the holder type of its kind.
     */
    abstract public function getClient(): UserInterface;

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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

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

    public function isRevoked(DateTimeInterface $now = new DateTimeImmutable()): bool
    {
        return null !== $this->dateRevoked && $this->dateRevoked <= $now;
    }

    public function isExpired(DateTimeInterface $now = new DateTimeImmutable()): bool
    {
        return null !== $this->dateExpiration && $this->dateExpiration <= $now;
    }

    public function isUsable(DateTimeInterface $now = new DateTimeImmutable()): bool
    {
        return ! $this->isRevoked($now) && ! $this->isExpired($now);
    }
}
