<?php

namespace Wexample\SymfonyApi\Controller;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Wexample\SymfonyApi\Api\Attribute\ValidateRequestContent;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Api\Dto\UserTokenIssueDto;
use Wexample\SymfonyApi\Entity\AbstractApiToken;
use Wexample\SymfonyApi\Entity\AbstractUserToken;
use Wexample\SymfonyApi\Helper\ApiHelper;
use Wexample\SymfonyApi\Security\AbstractApiTokenHandler;
use Wexample\SymfonyApi\Service\UserTokenService;

/**
 * A person's own tokens — listed, issued, revoked — for a screen of the
 * application to build on. Signed in with a session only: a token cannot
 * mint, list or revoke tokens, so a stolen one cannot outlive its revocation.
 * Routed only when the application imports `routes_user_tokens.yaml`.
 */
#[Route(path: '/api/user-tokens', name: 'api_user_tokens_')]
final class UserTokenApiController extends AbstractApiController
{
    public function __construct(
        private readonly UserTokenService $userTokenService,
    ) {
    }

    #[Route(path: '', name: 'list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] UserInterface $user): ApiResponse
    {
        $this->denyTokenCaller($request, $user);

        return self::apiResponseCollection(array_map(
            $this->normalizeToken(...),
            $this->userTokenService->findTokens($user)
        ));
    }

    /**
     * Answers the secret once: it is never readable again.
     */
    #[Route(path: '', name: 'issue', methods: ['POST'])]
    #[ValidateRequestContent(dto: UserTokenIssueDto::class)]
    public function issue(
        Request $request,
        #[CurrentUser] UserInterface $user,
        ?UserTokenIssueDto $content = null
    ): ApiResponse {
        $this->denyTokenCaller($request, $user);

        $expiresAt = null;

        if (null !== $content?->expiresAt) {
            $expiresAt = new DateTimeImmutable($content->expiresAt . ' 00:00:00');

            if ($expiresAt <= new DateTimeImmutable()) {
                throw new BadRequestHttpException('The expiration date must be in the future.');
            }
        }

        try {
            $secret = $this->userTokenService->issue($user, $expiresAt, $content?->label, $content?->scopes);
        } catch (InvalidArgumentException $exception) {
            // A scope the person lacks, an expiration beyond the maximum.
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
        $token = $this->userTokenService->findTokenBySecret($secret);

        return self::apiResponse(
            type: ApiHelper::RESPONSE_TYPE_SUCCESS,
            data: [
                'secret' => $secret,
                'token' => $this->normalizeToken($token),
            ],
            code: Response::HTTP_CREATED
        );
    }

    #[Route(path: '/{id}', name: 'revoke', methods: ['DELETE'])]
    public function revoke(
        Request $request,
        #[CurrentUser] UserInterface $user,
        string $id
    ): ApiResponse {
        $this->denyTokenCaller($request, $user);

        $token = $this->userTokenService->findTokenByReference($id);

        // Someone else's token answers as a missing one: ids are not probed.
        if (null === $token || $token->getClient()->getUserIdentifier() !== $user->getUserIdentifier()) {
            throw new NotFoundHttpException('No such token.');
        }

        $this->userTokenService->revoke($token);

        return self::apiResponseSuccess(data: $this->normalizeToken($token));
    }

    private function denyTokenCaller(Request $request, UserInterface $user): void
    {
        if ($request->attributes->has(AbstractApiTokenHandler::REQUEST_ATTRIBUTE_TOKEN)) {
            throw new AccessDeniedHttpException('Tokens are managed from a signed-in session.');
        }

        // A machine client, or an account of another provider than the one
        // the token class relates to.
        if (! $this->userTokenService->canHold($user)) {
            throw new AccessDeniedHttpException('This account cannot hold API tokens.');
        }
    }

    /**
     * Everything about a token but its secret, which is not stored, and its
     * hash.
     */
    private function normalizeToken(AbstractApiToken $token): array
    {
        $format = fn (?DateTimeInterface $date) => $date?->format(DATE_ATOM);

        return [
            'id' => (string) $token->getId(),
            'hint' => $token->getHint(),
            'label' => $token->getLabel(),
            'dateCreated' => $format($token->getDateCreated()),
            'dateExpiration' => $format($token->getDateExpiration()),
            'dateLastUsed' => $format($token->getDateLastUsed()),
            'dateRevoked' => $format($token->getDateRevoked()),
            'usable' => $token->isUsable(),
            'scopes' => $token instanceof AbstractUserToken ? $token->getScopes() : null,
        ];
    }
}
