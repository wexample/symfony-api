<?php

namespace Wexample\SymfonyApi\Tests\Application\Api\Controller;

use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyApi\Api\Controller\Test\QueryOptionController;
use Wexample\SymfonyApi\Tests\Class\AbstractApiApplicationTestCase;
use Wexample\SymfonyHelpers\Helper\DateHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

class QueryOptionControllerTest extends AbstractApiApplicationTestCase
{
    public function testYear()
    {
        $this->createGlobalClient();

        // Test missing query string.

        $this->goToRoute(
            QueryOptionController::buildRouteName(QueryOptionController::ROUTE_YEAR)
        );

        $this->assertStatusCodeEquals(
            Response::HTTP_BAD_REQUEST
        );

        $this->assertFalse(
            isset($this->applicationParseResponse()->message->year)
        );

        // Test valid request.
        $yearNowInt = DateHelper::getCurrentYearInt();

        $this->goToRoute(
            QueryOptionController::buildRouteName(QueryOptionController::ROUTE_YEAR), [
                VariableHelper::YEAR => $yearNowInt,
            ]
        );

        $this->assertStatusCodeOk();

        $this->assertEquals(
            $this->applicationParseResponse()->message->year,
            $yearNowInt.'-01-01'
        );
    }
}
