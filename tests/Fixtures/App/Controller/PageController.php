<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PageController
{
    #[Route(path: '/page', name: 'page')]
    public function page(): Response
    {
        return new Response('page');
    }
}
