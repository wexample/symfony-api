<?php

namespace Wexample\SymfonyApi\Api\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Helper\VariableHelper;


#[Route(path: 'api/', name: 'api_')]
class IndexApiController extends AbstractController
{
    final public const ROUTE_INDEX = VariableHelper::INDEX;

    #[Route(path:'', name: self::ROUTE_INDEX)]
    public function index(): Response
    {
        return new Response('OK');
    }
}
