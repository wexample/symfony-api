<?php

namespace Wexample\SymfonyApi\Api\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Helper\VariableHelper;


#[Route(path: 'api/', name: 'api_')]
class IndexApiController extends AbstractController
{
    final public const ROUTE_INDEX = VariableHelper::INDEX;

    #[Route(path: '', name: self::ROUTE_INDEX)]
    public function index(RouterInterface $router): Response
    {
        $output = '<h1>API</h1><table>';

        foreach ($router->getRouteCollection() as $route) {
            $path = $route->getPath();
            if (str_starts_with($path, '/api/')) {
                $output .= '<tr><td><a href="'.$path.'">'.$path.'</a></td></tr>';
            }
        }

        $output .= '</table>';

        return new Response($output);
    }
}
