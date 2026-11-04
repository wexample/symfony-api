<?php

namespace Wexample\SymfonyApi\Service;

use DateInterval;
use DateTimeImmutable;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Wexample\SymfonyApi\Enum\UserTokenSecurityEventType;
use Wexample\SymfonyApi\Exception\UserTokenLoginException;

/**
 * A user token issued for an identifier and a password, for the clients of
 * an api that sign in without a page: the account's provider finds it, its
 * password hasher checks it, the firewall's user checker admits it, and the
 * attempts are throttled per identifier and address.
 *
 * It does not ask a second factor: an application requiring one of its
 * accounts weighs that before enabling it.
 */
class UserTokenPasswordLoginService
{
    /**
     * A known string hashed once, so that an unknown identifier costs the
     * time a wrong password does.
     */
    private const string DUMMY_PASSWORD = 'not-a-password';

    public function __construct(
        private readonly UserTokenService $userTokenService,
        private readonly UserProviderInterface $userProvider,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        private readonly RateLimiterFactoryInterface $loginLimiter,
        private readonly MachineSecurityJournalService $journal,
        private readonly ?UserCheckerInterface $userChecker,
        private readonly ?string $lifetime,
        private readonly ?string $label,
    ) {
    }

    /**
     * @return array{0: string, 1: UserInterface} the token's secret, shown
     *     once, and the account it was issued to
     *
     * @throws UserTokenLoginException
     */
    public function login(
        string $identifier,
        string $password,
        ?string $clientIp
    ): array {
        $limiter = $this->loginLimiter->create(mb_strtolower(trim($identifier)) . '|' . $clientIp);
        $limit = $limiter->consume(0);

        if (0 === $limit->getRemainingTokens()) {
            $this->journal->record(UserTokenSecurityEventType::THROTTLED, cause: 'password_login');

            throw new UserTokenLoginException(UserTokenLoginException::REASON_THROTTLED, $limit->getRetryAfter());
        }

        $user = $this->authenticate($identifier, $password);

        if (null === $user) {
            $limiter->consume();
            $this->journal->record(UserTokenSecurityEventType::REFUSED, cause: 'password_login');

            throw new UserTokenLoginException(UserTokenLoginException::REASON_INVALID);
        }

        $limiter->reset();

        $secret = $this->userTokenService->issue(
            $user,
            null === $this->lifetime ? null : (new DateTimeImmutable())->add(new DateInterval($this->lifetime)),
            $this->label
        );

        return [$secret, $user];
    }

    private function authenticate(
        string $identifier,
        string $password
    ): ?UserInterface {
        try {
            $user = $this->userProvider->loadUserByIdentifier(trim($identifier));
        } catch (UserNotFoundException) {
            $this->passwordHasherFactory->getPasswordHasher(PasswordAuthenticatedUserInterface::class)
                ->verify($this->getDummyHash(), $password);

            return null;
        }

        if (! $user instanceof PasswordAuthenticatedUserInterface
            || null === $user->getPassword()
            || ! $this->passwordHasher->isPasswordValid($user, $password)) {
            return null;
        }

        try {
            $this->userChecker?->checkPreAuth($user);
            $this->userChecker?->checkPostAuth($user);
        } catch (AccountStatusException) {
            return null;
        }

        return $user;
    }

    private function getDummyHash(): string
    {
        static $hash = null;

        return $hash ??= $this->passwordHasherFactory
            ->getPasswordHasher(PasswordAuthenticatedUserInterface::class)
            ->hash(self::DUMMY_PASSWORD);
    }
}
