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

        $this->goToAndAssertError(
            QueryOptionController::class,
            QueryOptionController::ROUTE_LENGTH,
            VariableHelper::LENGTH
        );

        $this->goToAndAssertIntTypeRestriction(
            QueryOptionController::class,
            QueryOptionController::ROUTE_LENGTH,
            VariableHelper::LENGTH,
            'wrong-value-type'
        );

        // Test valid request.
        $this->goToAndAssertSuccess(
            QueryOptionController::class,
            QueryOptionController::ROUTE_LENGTH,
            VariableHelper::LENGTH,
            1,
            1
        );
    }
    public function testPage()
    {
        $this->createGlobalClient();

        $this->goToAndAssertError(
            QueryOptionController::class,
            QueryOptionController::ROUTE_PAGE,
            VariableHelper::PAGE
        );

        $this->goToAndAssertIntTypeRestriction(
            QueryOptionController::class,
            QueryOptionController::ROUTE_PAGE,
            VariableHelper::PAGE,
            'wrong-value-type'
        );

        // Test valid request.
        $this->goToAndAssertSuccess(
            QueryOptionController::class,
            QueryOptionController::ROUTE_PAGE,
            VariableHelper::PAGE,
            1,
            1
        );
    }

    public function testYear()
    {
        $this->createGlobalClient();

        $this->goToAndAssertError(
            QueryOptionController::class,
            QueryOptionController::ROUTE_YEAR,
            VariableHelper::YEAR
        );

        $yearNowInt = DateHelper::getCurrentYearInt();
        $this->goToAndAssertSuccess(
            QueryOptionController::class,
            QueryOptionController::ROUTE_YEAR,
            VariableHelper::YEAR,
            $yearNowInt,
            $yearNowInt.'-01-01'
        );
    }
}
