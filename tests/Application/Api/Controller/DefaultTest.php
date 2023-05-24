<?php

namespace Wexample\SymfonyApi\Tests\Application\Api\Controller;

use Wexample\SymfonyApi\Api\Controller\Test\ResponseController;
use Wexample\SymfonyApi\Tests\Class\AbstractApiApplicationTestCase;
use Wexample\SymfonyApi\Tests\Traits\TestCase\Application\ApiTestCaseTrait;

class DefaultTest extends AbstractApiApplicationTestCase
{
    use ApiTestCaseTrait;

    public function testFunctionality()
    {
        $this->createGlobalClient();

        $this->goToRoute(
            ResponseController::buildRouteName(ResponseController::ROUTE_SUCCESS)
        );

        $this->assertResponseIsSuccessful();
    }
}
