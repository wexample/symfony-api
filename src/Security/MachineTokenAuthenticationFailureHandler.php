<?php

namespace Wexample\SymfonyApi\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;

/**
 * The refusal of a machine firewall: a 401 in the API envelope, the same for
 * every bad token. Also its entry point, for a request carrying none.
 */
class MachineTokenAuthenticationFailureHandler implements
    AuthenticationFailureHandlerInterface,
    AuthenticationEntryPointInterface
{
    final public const string MESSAGE_INVALID = 'Invalid credentials.';

    final public const string MESSAGE_REQUIRED = 'Authentication required.';

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): Response {
        return $this->createResponse(
            self::MESSAGE_INVALID,
            'Bearer error="invalid_token"'
        );
    }

    public function start(
        Request $request,
        ?AuthenticationException $authException = null
    ): Response {
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
