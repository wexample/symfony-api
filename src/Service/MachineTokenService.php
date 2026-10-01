<?php

namespace Wexample\SymfonyApi\Service;

use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyApi\Entity\AbstractMachineToken;
use Wexample\SymfonyApi\Enum\MachineSecurityEventType;
use Wexample\SymfonyApi\Helper\MachineTokenHelper;
use Wexample\SymfonyApi\Interface\MachineClientInterface;

/**
 * The life of machine tokens: issued, rotated, revoked. The plain secret is
 * returned once, when it is made, and is never readable again.
 */
class MachineTokenService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MachineSecurityJournalService $journal,
        #[Autowire(param: 'api_machine_token_class')]
        private readonly ?string $tokenClass,
        #[Autowire(param: 'api_machine_token_prefix')]
        private readonly string $prefix,
    ) {
    }

    /**
     * Creates a token for the client and returns its secret, in plain, once.
     */
    public function issue(
        MachineClientInterface $client,
        ?DateTimeImmutable $dateExpiration = null,
        ?string $label = null
    ): string {
        [$plain, $token] = $this->createToken($client, $dateExpiration, $label);
        $this->entityManager->flush();

        $this->journal->record(MachineSecurityEventType::ISSUED, $client, $token->getHint(), extra: [
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
        MachineClientInterface $client,
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

        $this->journal->record(MachineSecurityEventType::ROTATED, $client, $token->getHint(), extra: [
            'label' => $label,
            'previous_tokens' => $previous,
            'previous_expire_at' => $overlapEnd->format(DATE_ATOM),
        ]);

        return $plain;
    }

    public function revoke(AbstractMachineToken $token): void
    {
        if ($token->isRevoked()) {
            return;
        }

        $token->revoke();
        $this->entityManager->flush();

        $this->journal->record(MachineSecurityEventType::REVOKED, $token->getClient(), $token->getHint());
    }

    /**
     * Every token of the client — a decommissioned or stolen one. Returns how
     * many were still unrevoked.
     */
    public function revokeAll(MachineClientInterface $client): int
    {
        $revoked = 0;

        foreach ($this->findTokens($client) as $token) {
            if (! $token->isRevoked()) {
                $token->revoke();
                $revoked++;
            }
        }

        $this->entityManager->flush();

        $this->journal->record(MachineSecurityEventType::REVOKED, $client, extra: [
            'scope' => 'all',
            'tokens' => $revoked,
        ]);

        return $revoked;
    }

    /**
     * @return list<AbstractMachineToken>
     */
    public function findTokens(MachineClientInterface $client): array
    {
        return $this->entityManager->getRepository($this->getTokenClass())->findBy(
            [$this->getClientPropertyName() => $client],
            ['dateCreated' => 'ASC']
        );
    }

    public function findTokenBySecret(string $plain): ?AbstractMachineToken
    {
        return $this->entityManager->getRepository($this->getTokenClass())->findOneBy([
            'tokenHash' => MachineTokenHelper::hashToken($plain),
        ]);
    }

    /**
     * A token named by its id or by its hint, with or without the ellipsis.
     */
    public function findTokenByReference(string $reference): ?AbstractMachineToken
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

    public function findClient(string $id): ?MachineClientInterface
    {
        return $this->entityManager->getRepository($this->getClientClass())->find($id);
    }

    /**
     * Gives the token a new secret and returns it in plain: the only time it
     * exists outside the client that will present it.
     */
    public function generateSecret(AbstractMachineToken $token): string
    {
        $plain = MachineTokenHelper::generateToken($this->prefix);

        $token
            ->setTokenHash(MachineTokenHelper::hashToken($plain))
            ->setHint(MachineTokenHelper::buildHint($plain, $this->prefix));

        return $plain;
    }

    /**
     * @return class-string<MachineClientInterface>
     */
    public function getClientClass(): string
    {
        return $this->getClientAssociation()['targetEntity'];
    }

    /**
     * @return array{0: string, 1: AbstractMachineToken}
     */
    private function createToken(
        MachineClientInterface $client,
        ?DateTimeImmutable $dateExpiration,
        ?string $label
    ): array {
        $class = $this->getTokenClass();

        /** @var AbstractMachineToken $token */
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
     * the one association whose target is a machine client.
     *
     * @return array{fieldName: string, targetEntity: class-string<MachineClientInterface>}
     */
    private function getClientAssociation(): array
    {
        $metadata = $this->entityManager->getClassMetadata($this->getTokenClass());

        foreach ($metadata->getAssociationNames() as $name) {
            $target = $metadata->getAssociationTargetClass($name);

            if ($metadata->isSingleValuedAssociation($name) && is_a($target, MachineClientInterface::class, true)) {
                return ['fieldName' => $name, 'targetEntity' => $target];
            }
        }

        throw new LogicException($this->getTokenClass() . ' maps no relation to a ' . MachineClientInterface::class . '.');
    }

    private function getTokenClass(): string
    {
        return $this->tokenClass
            ?? throw new LogicException('Set wexample_symfony_api.machine_token.token_class to use machine tokens.');
    }
}
