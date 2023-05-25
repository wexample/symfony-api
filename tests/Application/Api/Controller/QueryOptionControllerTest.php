<?php

namespace Wexample\SymfonyApi\Tests\Application\Api\Controller;

use Wexample\SymfonyApi\Api\Controller\Test\QueryOptionController;
use Wexample\SymfonyApi\Tests\Class\AbstractApiApplicationTestCase;
use Wexample\SymfonyHelpers\Helper\DateHelper;

class QueryOptionControllerTest extends AbstractApiApplicationTestCase
{
    public function testYear()
    {
        $this->createGlobalClient();

        $this->goToRoute(
            QueryOptionController::buildRouteName(QueryOptionController::ROUTE_YEAR)
        );

        $this->assertStatusCodeOk();

        $this->assertEquals(
            $this->applicationParseResponse()->message->year,
            DateHelper::getCurrentYearInt() . '-01-01'
        );
    }
}
