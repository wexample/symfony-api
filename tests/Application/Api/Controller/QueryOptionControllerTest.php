<?php

namespace Wexample\SymfonyApi\Tests\Application\Api\Controller;

use Wexample\SymfonyApi\Api\Class\AbstractApiResponseMember;
use Wexample\SymfonyApi\Api\Controller\Test\QueryOptionController;
use Wexample\SymfonyApi\Helper\ApiHelper;
use Wexample\SymfonyApi\Tests\Class\AbstractApiApplicationTestCase;
use Wexample\SymfonyApi\Tests\Traits\TestCase\TextManipulationTestCaseTrait;
use Wexample\SymfonyHelpers\Helper\DateHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

class QueryOptionControllerTest extends AbstractApiApplicationTestCase
{
    use TextManipulationTestCaseTrait;

    public function testDisplayFormat()
    {
        $this->createGlobalClient();

        $this->goToAndAssertErrorWhenMissing(
            QueryOptionController::class,
            QueryOptionController::ROUTE_DISPLAY_FORMAT,
            ApiHelper::FILTER_TAG
        );

        $this->goToAndAssertSuccess(
            QueryOptionController::class,
            QueryOptionController::ROUTE_DISPLAY_FORMAT,
            ApiHelper::_KEBAB_DISPLAY_FORMAT,
            AbstractApiResponseMember::DISPLAY_FORMAT_FULL,
            AbstractApiResponseMember::DISPLAY_FORMAT_FULL,
        );

        $this->goToAndAssertFailedTypeRestriction(
            QueryOptionController::class,
            QueryOptionController::ROUTE_DISPLAY_FORMAT,
            ApiHelper::_KEBAB_DISPLAY_FORMAT,
            'wrong-display-format'
        );

        $this->goToAndAssertFailedTypeRestriction(
            QueryOptionController::class,
            QueryOptionController::ROUTE_DISPLAY_FORMAT,
            ApiHelper::_KEBAB_DISPLAY_FORMAT,
            $this->fuzzerString()
        );
    }

    public function testFilterTag()
    {
        $this->createGlobalClient();

        $this->goToAndAssertErrorWhenMissing(
            QueryOptionController::class,
            QueryOptionController::ROUTE_FILTER_TAG,
            ApiHelper::FILTER_TAG
        );

        $this->goToAndAssertSuccess(
            QueryOptionController::class,
            QueryOptionController::ROUTE_FILTER_TAG,
            ApiHelper::_KEBAB_FILTER_TAG,
            'test-filter-tag',
            'test-filter-tag',
        );
    }

    public function testId()
    {
        $this->createGlobalClient();

        $this->checkApiQueryOptionIntType(
            QueryOptionController::class,
            QueryOptionController::ROUTE_ID,
            VariableHelper::ID,
            15,
            15
        );
    }
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
