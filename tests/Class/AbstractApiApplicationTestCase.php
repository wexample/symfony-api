<?php

namespace Wexample\SymfonyApi\Tests\Class;

use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyApi\Tests\Traits\TestCase\Application\ApiTestCaseTrait;
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
}