<?php

namespace Wexample\SymfonyApi\Api\Controller\Traits;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\LengthQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\PageQueryOption;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Voter\AbstractEntityVoter;

/**
 * `GET list`: a page of the entities the caller may view, counted on the
 * same query.
 *
 * The query is what scopes the list — createApiEntityListQueryBuilder(),
 * every row by default. The voter only guards it: a page holding a row the
 * caller may not view is refused whole, rather than thinned, which would
 * leave a total and pages counting what it hides. A list sorted by `?sort=`
 * redeclares the route with its #[SortQueryOption] and calls
 * listApiEntities().
 */
trait EntityListApiControllerTrait
{
    use EntityApiControllerTrait;

    #[Route(
        path: AbstractController::DEFAULT_ROUTE_NAME_LIST,
        name: AbstractController::DEFAULT_ROUTE_NAME_LIST,
        methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_GET
    )]
    #[PageQueryOption]
    #[LengthQueryOption]
    public function list(Request $request): ApiResponse
    {
        return $this->listApiEntities($request);
    }

    protected function listApiEntities(Request $request): ApiResponse
    {
        return self::apiResponseQueryPage(
            $request,
            $this->createApiEntityListQueryBuilder(),
            function (array $entities): array {
                foreach ($entities as $entity) {
                    if (! $this->isGranted(AbstractEntityVoter::VIEW, $entity)) {
                        throw new AccessDeniedException(sprintf('The list of %s returned a row the caller may not view: scope %s::createApiEntityListQueryBuilder().', $entity::class, static::class));
                    }
                }

                return $this->normalizeApiEntities($entities);
            }
        );
    }

    protected function createApiEntityListQueryBuilder(): QueryBuilder
    {
        return $this->getApiEntityRepository()->createQueryBuilder('entity');
    }
}
