<?php

namespace Wexample\SymfonyApi\Api\Controller\Traits;

use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyHelpers\Controller\AbstractController;

/**
 * `GET show/{identifier}`: the entity, if the caller may view it.
 */
trait EntityShowApiControllerTrait
{
    use EntityApiControllerTrait;

    #[Route(
        path: AbstractController::DEFAULT_ROUTE_NAME_SHOW . '/{identifier}',
        name: AbstractController::DEFAULT_ROUTE_NAME_SHOW,
        methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_GET
    )]
    public function show(string $identifier): ApiResponse
    {
        return self::apiResponseSuccess(
            data: $this->normalizeApiEntity($this->getApiEntity($identifier))
        );
    }
}
