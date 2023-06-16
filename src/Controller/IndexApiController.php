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
        $output = '<h1>API</h1>';

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
                $queryParametersString = implode(',', $queryParametersString);

                $requirements = $route->getRequirements();
                $requirementsString = implode(', ', array_map(
                    function(
                        $v,
                        $k
                    ) {
                        return sprintf("%s: %s", $k, $v);
                    },
                    $requirements,
                    array_keys($requirements)
                ));

                $output .= '<p>'.
                    '<h2><a href="'.$path.'">'.$path.'</a></h2>'.
                    $requirementsString.'<br>'.
                    $queryParametersString.
                    '</p>';
            }
        }

        return new Response($output);
    }
}
