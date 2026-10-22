<?php

namespace Wexample\SymfonyApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyApi\Helper\ApiResponseNormHelper;

class ApiResponseNormHelperTest extends TestCase
{
    public function testAPageOfEntitiesHoldsTheNorm(): void
    {
        $this->assertSame([], ApiResponseNormHelper::problems([
            'type' => 'success',
            'code' => 200,
            'data' => [
                'items' => [
                    ['type' => 'patient', 'entity' => ['id' => 'a1', 'name' => 'Ada'], 'metadata' => [], 'relationships' => []],
                    ['type' => 'patient', 'entity' => ['id' => 7]],
                ],
                'pagination' => ['page' => 0, 'length' => 10, 'total' => 2, 'pagesCount' => 1, 'hasMore' => false],
            ],
        ]));
        $this->assertSame([], ApiResponseNormHelper::problems(['type' => 'error', 'code' => 400, 'message' => 'Nope', 'data' => []]));
    }

    public function testEachDepartureIsNamed(): void
    {
        $this->assertSame(['not an object'], ApiResponseNormHelper::problems('ok'));
        $this->assertSame(['`type` is neither success nor error', 'no integer `code`', 'no `data`'], ApiResponseNormHelper::problems(['ok' => true]));
        $item = static fn (array $item) => ApiResponseNormHelper::problems(['type' => 'success', 'code' => 200, 'data' => ['items' => [$item]]]);
        $this->assertSame(['`data.items[0]` has no `type`'], $item(['id' => 1, 'name' => 'flat']));
        $this->assertSame(['`data.items[0]` has no `entity` object'], $item(['type' => 'patient']));
        $this->assertSame(['`data.items[0]` has an `entity` without `id`'], $item(['type' => 'patient', 'entity' => ['name' => 'Ada']]));
        $this->assertSame(['`data.items` is not a list', '`data.pagination` lacks pagesCount, hasMore'], ApiResponseNormHelper::problems([
            'type' => 'success', 'code' => 200, 'data' => ['items' => ['a' => ['id' => 1]], 'pagination' => ['page' => 0, 'length' => 1, 'total' => 1]],
        ]));
    }
}
