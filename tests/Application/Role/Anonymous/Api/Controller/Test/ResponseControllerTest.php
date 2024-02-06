<?php

namespace Wexample\SymfonyApi\Tests\Application\Role\Anonymous\Api\Controller\Test;

use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyApi\Api\Controller\Test\ResponseController;
use Wexample\SymfonyApi\Tests\Traits\TestCase\Application\ApiTestCaseTrait;
use Wexample\SymfonyApi\Traits\SymfonyApiBundleClassTrait;
use Wexample\SymfonyTesting\Tests\AbstractRoleControllerTestCase;
use Wexample\SymfonyTesting\Tests\Traits\RoleAnonymousTestCaseTrait;

class ResponseControllerTest extends AbstractRoleControllerTestCase
{
    use RoleAnonymousTestCaseTrait;
    use ApiTestCaseTrait;
    use SymfonyApiBundleClassTrait;

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
