<?php

namespace Wexample\SymfonyApi\Tests\Application\Api\Controller;

use Wexample\SymfonyApi\Api\Controller\Test\QueryOptionController;
use Wexample\SymfonyApi\Tests\Class\AbstractApiApplicationTestCase;
use Wexample\SymfonyHelpers\Helper\DateHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

class QueryOptionControllerTest extends AbstractApiApplicationTestCase
{
    public function testLength()
    {
        $this->createGlobalClient();

        $this->checkApiQueryOptionIntType(
            QueryOptionController::class,
            QueryOptionController::ROUTE_LENGTH,
            VariableHelper::LENGTH,
            15,
            15
        );
    }
    public function testPage()
    {
        $this->createGlobalClient();

        $this->checkApiQueryOptionIntType(
            QueryOptionController::class,
            QueryOptionController::ROUTE_PAGE,
            VariableHelper::PAGE,
            15,
            15
        );
    }

    public function testYear()
    {
        $this->createGlobalClient();

        $yearNowInt = DateHelper::getCurrentYearInt();
        $this->checkApiQueryOptionIntType(
            QueryOptionController::class,
            QueryOptionController::ROUTE_YEAR,
            VariableHelper::YEAR,
            $yearNowInt,
            $yearNowInt.'-01-01'
        );
    }
}
