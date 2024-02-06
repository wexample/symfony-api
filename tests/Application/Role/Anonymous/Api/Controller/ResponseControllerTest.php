<?php

namespace Wexample\SymfonyApi\Tests\Application\Role\Anonymous\Api\Controller;

use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyApi\Api\Controller\Test\ResponseController;
use Wexample\SymfonyApi\Tests\Traits\TestCase\Application\ApiTestCaseTrait;
use Wexample\SymfonyTesting\Tests\AbstractRoleControllerTestCase;
use Wexample\SymfonyTesting\Tests\Traits\AbstractAnonymousTestCaseTrait;

class ResponseControllerTest extends AbstractRoleControllerTestCase
{
    use AbstractAnonymousTestCaseTrait;
    use ApiTestCaseTrait;

    public function testSuccess()
    {
        $this->createGlobalClient();

        $this->goToRoute(
            ResponseController::buildRouteName(ResponseController::ROUTE_SUCCESS)
        );

        $this->assertStatusCodeOk();
    }

    public function testError()
    {
        $this->createGlobalClient();

        $this->goToRoute(
            ResponseController::buildRouteName(ResponseController::ROUTE_ERROR)
        );

        $this->assertStatusCodeEquals(
            Response::HTTP_BAD_REQUEST
        );
    }
}
