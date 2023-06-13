<?php

namespace Wexample\SymfonyApi\Tests\Application\Api\Controller;

use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyApi\Api\Class\AbstractApiResponseMember;
use Wexample\SymfonyApi\Api\Controller\Test\QueryOptionController;
use Wexample\SymfonyApi\Helper\ApiHelper;
use Wexample\SymfonyApi\Tests\Class\AbstractApiApplicationTestCase;
use Wexample\SymfonyApi\Tests\Traits\TestCase\TextManipulationTestCaseTrait;
use Wexample\SymfonyHelpers\Helper\ArrayHelper;
use Wexample\SymfonyHelpers\Helper\DateHelper;
use Wexample\SymfonyHelpers\Helper\TypesHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

class QueryOptionControllerTest extends AbstractApiApplicationTestCase
{
    use TextManipulationTestCaseTrait;

    public function testCustom()
    {
        $this->createGlobalClient();

        # TODO Testing failure in CI.
        $this->assertTrue(false);

        $failingCombinations = ArrayHelper::generateAllIncompleteCombinations(
            QueryOptionController::TEST_CUSTOM_TYPES
        );

        $routeName = QueryOptionController::buildRouteName(
            QueryOptionController::ROUTE_CUSTOM
        );

        $testValidValues = [
            TypesHelper::BOOLEAN => true,
            TypesHelper::INTEGER => 123,
            TypesHelper::STRING => 'lorem-ipsum'
        ];

        foreach ($failingCombinations as $combination) {
            $data = [];

            foreach ($combination as $combinationType) {
                $data[VariableHelper::CUSTOM.'-'.$combinationType] = $testValidValues[$combinationType];
            }

            $this->goToRoute(
                $routeName,
                $data
            );

            // It should fail as every parameter is expected.
            $this->assertStatusCodeEquals(
                Response::HTTP_BAD_REQUEST
            );
        }

        // Success
        $data = [];
        foreach ($testValidValues as $type => $value) {
            $data[VariableHelper::CUSTOM.'-'.$type] = $value;
        }

        $this->goToRoute(
            $routeName,
            $data
        );

        $this->assertStatusCodeOk();
    }

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
