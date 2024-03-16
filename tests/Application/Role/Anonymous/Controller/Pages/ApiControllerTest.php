<?php

namespace Wexample\SymfonyApi\Tests\Application\Role\Anonymous\Controller\Pages;

use Wexample\SymfonyApi\Controller\Pages\ApiController;
use Wexample\SymfonyApi\Traits\SymfonyApiBundleClassTrait;
use Wexample\SymfonyTesting\Tests\AbstractRoleControllerTestCase;
use Wexample\SymfonyTesting\Tests\Traits\RoleAnonymousTestCaseTrait;
use Wexample\SymfonyTesting\Traits\ControllerTestCaseTrait;

class ApiControllerTest extends AbstractRoleControllerTestCase
{
    use RoleAnonymousTestCaseTrait;
    use ControllerTestCaseTrait;
    use SymfonyApiBundleClassTrait;

    public function testIndex()
    {
        $this->goToControllerRouteAndCheckHtml(
            ApiController::ROUTE_INDEX
        );
    }
}