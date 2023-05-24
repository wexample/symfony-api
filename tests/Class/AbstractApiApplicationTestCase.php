<?php

namespace Wexample\SymfonyApi\Tests\Class;

use Wexample\SymfonyApi\Tests\Traits\TestCase\Application\ApiTestCaseTrait;
use Wexample\SymfonyHelpers\Tests\Class\AbstractApplicationTestCase;

abstract class AbstractApiApplicationTestCase extends AbstractApplicationTestCase
{
    use ApiTestCaseTrait;
}