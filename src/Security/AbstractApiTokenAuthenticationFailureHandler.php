<?php

namespace Wexample\SymfonyApi\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Enum\MachineSecurityEventType;
use Wexample\SymfonyApi\Enum\MachineTokenRefusalCause;
use Wexample\SymfonyApi\Enum\UserTokenSecurityEventType;
use Wexample\SymfonyApi\Exception\MachineTokenThrottledException;
use Wexample\SymfonyApi\Service\MachineSecurityJournalService;

/**
 * The refusal of a token firewall: a 401 in the API envelope, the same for
 * every bad token. Also its entry point, for a request carrying none. One
 * subclass per kind of token, for the journal.
 */
abstract class AbstractApiTokenAuthenticationFailureHandler implements
    AuthenticationFailureHandlerInterface,
    AuthenticationEntryPointInterface
{
    final public const string MESSAGE_INVALID = 'Invalid credentials.';

    final public const string MESSAGE_REQUIRED = 'Authentication required.';

    final public const string MESSAGE_THROTTLED = 'Too many requests.';

    public function __construct(
        private readonly MachineSecurityJournalService $journal,
    ) {
    }

    /**
     * @return class-string<MachineSecurityEventType|UserTokenSecurityEventType>
     */
    abstract protected function getEventTypeClass(): string;

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): Response {
        for ($previous = $exception; null !== $previous; $previous = $previous->getPrevious()) {
            if ($previous instanceof MachineTokenThrottledException) {
                // The same for every caller: it tells nothing of the token.
                $response = AbstractApiController::apiResponseError(
                    message: self::MESSAGE_THROTTLED,
                    code: Response::HTTP_TOO_MANY_REQUESTS
                )->toJsonResponse();
                $response->headers->set('Retry-After', (string) max(1, $previous->retryAfter->getTimestamp() - time()));

                return $response;
            }
        }

        return $this->createResponse(
            self::MESSAGE_INVALID,
            'Bearer error="invalid_token"'
        );
    }

    public function start(
        Request $request,
        ?AuthenticationException $authException = null
    ): Response {
        // A presented token that failed is journalled by the failure event.
        $this->journal->record(
            $this->getEventTypeClass()::REFUSED,
            cause: MachineTokenRefusalCause::MISSING->value
        );

        return $this->createResponse(
            self::MESSAGE_REQUIRED,
            'Bearer'
        );
    }

    private function createResponse(
        string $message,
        string $challenge
    ): Response {
        $response = AbstractApiController::apiResponseError(
            message: $message,
            code: Response::HTTP_UNAUTHORIZED
        )->toJsonResponse();

        $response->headers->set('WWW-Authenticate', $challenge);

        return $response;
    }
}
