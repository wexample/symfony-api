<?php

namespace Wexample\SymfonyApi\Service;

use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyApi\Entity\AbstractApiToken;
use Wexample\SymfonyApi\Enum\MachineSecurityEventType;
use Wexample\SymfonyApi\Enum\UserTokenSecurityEventType;
use Wexample\SymfonyApi\Helper\MachineTokenHelper;

/**
 * The life of API tokens of one kind: issued, rotated, revoked. The plain
 * secret is returned once, when it is made, and is never readable again.
 */
abstract class AbstractApiTokenService
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly MachineSecurityJournalService $journal,
        protected readonly ?string $tokenClass,
        protected readonly string $prefix,
    ) {
    }

    /**
     * What a holder of this kind implements; the token class maps one
     * relation to it.
     *
     * @return class-string<UserInterface>
     */
    abstract protected function getClientInterface(): string;

    /**
     * Whether this user may hold tokens of this kind: an instance of the
     * entity the token class relates to.
     */
    public function canHold(UserInterface $client): bool
    {
        return $client instanceof ($this->getClientClass());
    }

    /**
     * @return class-string<MachineSecurityEventType|UserTokenSecurityEventType>
     */
    abstract protected function getEventTypeClass(): string;

    /**
     * The configuration section of this kind, for the error messages.
     */
    abstract protected function getConfigurationKey(): string;

    /**
     * Creates a token for the client and returns its secret, in plain, once.
     */
    public function issue(
        UserInterface $client,
        ?DateTimeImmutable $dateExpiration = null,
        ?string $label = null
    ): string {
        [$plain, $token] = $this->createToken($client, $dateExpiration, $label);
        $this->entityManager->flush();

        $this->journal->record($this->getEventTypeClass()::ISSUED, $client, $token->getHint(), extra: [
            'label' => $label,
        ]);

        return $plain;
    }

    /**
     * Issues a new token and lets the client's other ones expire after the
     * overlap, so the client switches over without being locked out. Returns
     * the new secret, in plain, once.
     */
    public function rotate(
        UserInterface $client,
        DateInterval $overlap,
        ?DateTimeImmutable $dateExpiration = null,
        ?string $label = null
    ): string {
        $now = new DateTimeImmutable();
        $overlapEnd = $now->add($overlap);
        $previous = 0;

        foreach ($this->findTokens($client) as $token) {
            if (! $token->isUsable($now)) {
                continue;
            }

            if (null === $token->getDateExpiration() || $token->getDateExpiration() > $overlapEnd) {
                $token->setDateExpiration($overlapEnd);
            }

            $previous++;
        }

        [$plain, $token] = $this->createToken($client, $dateExpiration, $label);
        $this->entityManager->flush();

        $this->journal->record($this->getEventTypeClass()::ROTATED, $client, $token->getHint(), extra: [
            'label' => $label,
            'previous_tokens' => $previous,
            'previous_expire_at' => $overlapEnd->format(DATE_ATOM),
        ]);

        return $plain;
    }

    public function revoke(AbstractApiToken $token): void
    {
        if ($token->isRevoked()) {
            return;
        }

        $token->revoke();
        $this->entityManager->flush();

        $this->journal->record($this->getEventTypeClass()::REVOKED, $token->getClient(), $token->getHint());
    }

    /**
     * Every token of the client — a decommissioned or stolen one. Returns how
     * many were still unrevoked.
     */
    public function revokeAll(UserInterface $client): int
    {
        $revoked = 0;

        foreach ($this->findTokens($client) as $token) {
            if (! $token->isRevoked()) {
                $token->revoke();
                $revoked++;
            }
        }

        $this->entityManager->flush();

        $this->journal->record($this->getEventTypeClass()::REVOKED, $client, extra: [
            'scope' => 'all',
            'tokens' => $revoked,
        ]);

        return $revoked;
    }

    /**
     * @return list<AbstractApiToken>
     */
    public function findTokens(UserInterface $client): array
    {
        return $this->entityManager->getRepository($this->getTokenClass())->findBy(
            [$this->getClientPropertyName() => $client],
            ['dateCreated' => 'ASC']
        );
    }

    public function findTokenBySecret(string $plain): ?AbstractApiToken
    {
        return $this->entityManager->getRepository($this->getTokenClass())->findOneBy([
            'tokenHash' => MachineTokenHelper::hashToken($plain),
        ]);
    }

    /**
     * A token named by its id or by its hint, with or without the ellipsis.
     */
    public function findTokenByReference(string $reference): ?AbstractApiToken
    {
        $repository = $this->entityManager->getRepository($this->getTokenClass());

        if (str_starts_with($reference, $this->prefix)) {
            $tokens = $repository->findBy(['hint' => rtrim($reference, '…') . '…']);

            if (count($tokens) > 1) {
                throw new InvalidArgumentException('Several tokens share the hint "' . $reference . '": name it by its id.');
            }

            return $tokens[0] ?? null;
        }

        return $repository->find($reference);
    }

    public function findClient(string $id): ?UserInterface
    {
        return $this->entityManager->getRepository($this->getClientClass())->find($id);
    }

    /**
     * Gives the token a new secret and returns it in plain: the only time it
     * exists outside the client that will present it.
     */
    public function generateSecret(AbstractApiToken $token): string
    {
        $plain = MachineTokenHelper::generateToken($this->prefix);

        $token
            ->setTokenHash(MachineTokenHelper::hashToken($plain))
            ->setHint(MachineTokenHelper::buildHint($plain, $this->prefix));

        return $plain;
    }

    /**
     * @return class-string<UserInterface>
     */
    public function getClientClass(): string
    {
        return $this->getClientAssociation()['targetEntity'];
    }

    /**
     * @return array{0: string, 1: AbstractApiToken}
     */
    private function createToken(
        UserInterface $client,
        ?DateTimeImmutable $dateExpiration,
        ?string $label
    ): array {
        if (! $this->canHold($client)) {
            throw new InvalidArgumentException($client::class . ' cannot hold ' . $this->getConfigurationKey() . ' tokens.');
        }

        $class = $this->getTokenClass();

        /** @var AbstractApiToken $token */
        $token = (new $class())
            ->setClient($client)
            ->setDateExpiration($dateExpiration)
            ->setLabel($label);

        $plain = $this->generateSecret($token);
        $this->entityManager->persist($token);

        return [$plain, $token];
    }

    private function getClientPropertyName(): string
    {
        return $this->getClientAssociation()['fieldName'];
    }

    /**
     * The relation of the token class to its client, read from its mapping:
     * the one association whose target is a holder of this kind.
     *
     * @return array{fieldName: string, targetEntity: class-string<UserInterface>}
     */
    private function getClientAssociation(): array
    {
        $metadata = $this->entityManager->getClassMetadata($this->getTokenClass());

        foreach ($metadata->getAssociationNames() as $name) {
            $target = $metadata->getAssociationTargetClass($name);

            if ($metadata->isSingleValuedAssociation($name) && is_a($target, $this->getClientInterface(), true)) {
                return ['fieldName' => $name, 'targetEntity' => $target];
            }
        }

        throw new LogicException($this->getTokenClass() . ' maps no relation to a ' . $this->getClientInterface() . '.');
    }

    private function getTokenClass(): string
    {
        return $this->tokenClass
            ?? throw new LogicException('Set wexample_symfony_api.' . $this->getConfigurationKey() . '.token_class to use these tokens.');
    }
}
