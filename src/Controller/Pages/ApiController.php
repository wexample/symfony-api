<?php

namespace Wexample\SymfonyApi\Controller\Pages;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionConstrainedTrait;
use Wexample\SymfonyApi\Traits\SymfonyApiBundleClassTrait;
use Wexample\SymfonyLoader\Controller\AbstractPagesController;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

#[Route(path: 'api/', name: 'api_')]
final class ApiController extends AbstractPagesController
{
    use SymfonyApiBundleClassTrait;

    final public const ROUTE_INDEX = VariableHelper::INDEX;

    #[Route(name: self::ROUTE_INDEX)]
    public function index(RouterInterface $router): Response
    {
        $apiRoutes = [];

        foreach ($router->getRouteCollection() as $name => $route) {
            $path = $route->getPath();

            if (str_starts_with($path, '/api/')) {
                $apiQueryAttributes = ClassHelper::getChildrenAttributes(
                    $route->getDefaults()['_controller'],
                    QueryOptionConstrainedTrait::class
                );

                $queryParametersString = [];
                foreach ($apiQueryAttributes as $queryOption) {
                    $queryParametersString[] = $queryOption->newInstance()->key;
                }

                $requirements = $route->getRequirements();

                $apiRoutes[$path] = [
                    'name' => $name,
                    'path' => $path,
                    'requirements' => $requirements,
                    'queryParameters' => $queryParametersString,
                ];
            }
        }

        ksort($apiRoutes);

        return $this->renderPage(
            self::ROUTE_INDEX,
            [
                'apiRoutes' => $apiRoutes,
            ]
        );
    }
}
