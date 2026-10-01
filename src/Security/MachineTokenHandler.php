<?php

namespace Wexample\SymfonyApi\Security;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Wexample\SymfonyApi\Entity\AbstractMachineToken;
use Wexample\SymfonyApi\Helper\MachineTokenHelper;

/**
 * Resolves the bearer token of a machine firewall to its client, for the
 * native `access_token` authenticator of Symfony.
 *
 * Unknown, revoked and expired tokens fail the same way: the response tells a
 * caller nothing about which tokens exist.
 */
class MachineTokenHandler implements AccessTokenHandlerInterface
{
    /**
     * @param list<string> $allowedRoles
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'api_machine_token_class')]
        private readonly ?string $tokenClass,
        #[Autowire(param: 'api_machine_token_roles')]
        private readonly array $allowedRoles,
        #[Autowire(param: 'api_machine_token_last_used_interval')]
        private readonly int $lastUsedInterval,
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        if (null === $this->tokenClass) {
            throw new LogicException('Set wexample_symfony_api.machine_token.token_class to authenticate machine clients.');
        }

        /** @var AbstractMachineToken|null $token */
        $token = $this->entityManager
            ->getRepository($this->tokenClass)
            ->findOneBy(['tokenHash' => MachineTokenHelper::hashToken($accessToken)]);

        $now = new DateTimeImmutable();

        if (null === $token || ! $token->isUsable($now)) {
            throw new BadCredentialsException('Invalid machine token.');
        }

        $client = $token->getClient();
        $forbiddenRoles = array_diff($client->getRoles(), $this->allowedRoles);

        // A machine client carrying a role meant for people is a configuration
        // mistake: refuse it rather than open the doors that role opens.
        if (! empty($forbiddenRoles)) {
            $this->logger->error('Machine client refused: it carries roles outside wexample_symfony_api.machine_token.roles.', [
                'token' => $token->getHint(),
                'roles' => array_values($forbiddenRoles),
            ]);

            throw new BadCredentialsException('Invalid machine token.');
        }

        $this->touch($token, $now);

        return new UserBadge(
            $client->getUserIdentifier(),
            fn () => $client
        );
    }

    /**
     * One write per interval, not one per request.
     */
    private function touch(
        AbstractMachineToken $token,
        DateTimeImmutable $now
    ): void {
        $lastUsed = $token->getDateLastUsed();

        if (null !== $lastUsed && $now->getTimestamp() - $lastUsed->getTimestamp() < $this->lastUsedInterval) {
            return;
        }

        $token->setDateLastUsed($now);
        $this->entityManager->flush();
    }
}
