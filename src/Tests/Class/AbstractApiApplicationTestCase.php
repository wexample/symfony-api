<?php

namespace Wexample\SymfonyApi\Tests\Class;

use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyApi\Tests\Traits\TestCase\Application\ApiTestCaseTrait;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Helper\TextHelper;
use Wexample\SymfonyHelpers\Tests\Class\AbstractApplicationTestCase;

abstract class AbstractApiApplicationTestCase extends AbstractApplicationTestCase
{
    use ApiTestCaseTrait;

    public function applicationParseResponse(Response $response = null): object
    {
        return $this->apiParseResponse(
            $response ?? $this->getGlobalClientResponse()
        );
    }

    protected function goToSimpleRouteAndAssertFailedTypeRestriction(
        string|AbstractController $controller,
        string $route,
        string $queryStringKey,
        string|int|float|bool $sentValue
    ): void {
        $this->goToRoute(
            $controller::buildRouteName($route), [
                $queryStringKey => $sentValue,
            ]
        );

        $this->assertStatusCodeEquals(
            Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * Using a simple tests mechanism,
     * checks that sent query string returns expected value in response.
     */
    protected function goToSimpleRouteAndAssertSuccess(
        string|AbstractController $controller,
        string $route,
        string $queryStringKey,
        string|int|float|bool $sentValue,
        string|int|float|bool $expectedValue
    ): void {

        $this->goToRoute(
            $controller::buildRouteName($route), [
                $queryStringKey => $sentValue,
            ]
        );

        $this->assertStatusCodeOk();

        // Response keys should be in snake case
        $messageKey = TextHelper::toSnake($queryStringKey);
        $this->assertEquals(
            $this->applicationParseResponse()->message->$messageKey,
            $expectedValue
        );
    }

    /**
     * Using a simple tests mechanism,
     * checks that an error is return when query string is missing.
     */
    protected function goToSimpleRouteAndAssertErrorWhenMissing(
        string|AbstractController $controller,
        string $route,
        string $messageKey
    ): void {
        $this->goToRoute(
            $controller::buildRouteName(
                $route
            )
        );

        $this->assertStatusCodeEquals(
            Response::HTTP_BAD_REQUEST
        );

        // Response keys should be in snake case
        $this->assertFalse(
            isset($this->applicationParseResponse()->message->$messageKey),
            'Missing : '.$messageKey
        );
    }

    protected function checkApiQueryOptionIntType(
        string|AbstractController $controller,
        string $route,
        string $queryStringKey,
        string|int|float|bool $sentValue,
        string|int|float|bool $expectedValue
    ): void {
        $this->goToSimpleRouteAndAssertErrorWhenMissing(
            $controller,
            $route,
            $queryStringKey
        );

        $this->goToSimpleRouteAndAssertFailedTypeRestriction(
            $controller,
            $route,
            $queryStringKey,
            'wrong-value-type'
        );

        // Test valid request.
        $this->goToSimpleRouteAndAssertSuccess(
            $controller,
            $route,
            $queryStringKey,
            $sentValue,
            $expectedValue
        );
    }
}