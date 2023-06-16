<?php

namespace Wexample\SymfonyApi\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionTrait;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;


#[Route(path: 'api/', name: 'api_')]
class IndexApiController extends AbstractController
{
    final public const ROUTE_INDEX = VariableHelper::INDEX;

    #[Route(name: self::ROUTE_INDEX)]
    public function index(RouterInterface $router): Response
    {
        $apiRoutes = [];

        foreach ($router->getRouteCollection() as $route) {
            $path = $route->getPath();

            if (str_starts_with($path, '/api/')) {
                $apiQueryAttributes = ClassHelper::getChildrenAttributes(
                    $route->getDefaults()['_controller'],
                    QueryOptionTrait::class
                );

                $queryParametersString = [];
                foreach ($apiQueryAttributes as $queryOption) {
                    $queryParametersString[] = $queryOption->newInstance()->key;
                }

                $requirements = $route->getRequirements();

                $apiRoutes[] = [
                    'path' => $path,
                    'requirements' => $requirements,
                    'queryParameters' => $queryParametersString,
                ];
            }
        }

        return $this->render(
            '@WexampleSymfonyApiBundle/pages/index.html.twig', [
                'apiRoutes' => $apiRoutes,
            ]
        );
    }
}
