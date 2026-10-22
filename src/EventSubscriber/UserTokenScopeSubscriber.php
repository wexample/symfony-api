<?php

namespace Wexample\SymfonyApi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Wexample\SymfonyApi\Entity\AbstractUserToken;
use Wexample\SymfonyApi\Security\AbstractApiTokenHandler;
use Wexample\SymfonyApi\Service\UserTokenService;

/**
 * Narrows the roles of a request authenticated by a scoped user token to its
 * scopes: access control and voters read the security token's roles, not
 * the user's, so nothing else has to know.
 */
class UserTokenScopeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UserTokenService $userTokenService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [AuthenticationTokenCreatedEvent::class => 'onTokenCreated'];
    }

    public function onTokenCreated(AuthenticationTokenCreatedEvent $event): void
    {
        $token = $this->requestStack->getMainRequest()?->attributes->get(AbstractApiTokenHandler::REQUEST_ATTRIBUTE_TOKEN);
        $authenticated = $event->getAuthenticatedToken();

        if (! $token instanceof AbstractUserToken || ! $authenticated instanceof PostAuthenticationToken) {
            return;
        }

        $roles = $this->userTokenService->resolveRoles($token);

        if (null === $roles) {
            return;
        }

        $event->setAuthenticatedToken(new PostAuthenticationToken(
            $authenticated->getUser(),
            $authenticated->getFirewallName(),
            $roles
        ));
    }
}
