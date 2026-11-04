<?php

namespace Wexample\SymfonyApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyApi\Helper\ApiVersionHelper;

class ApiVersionHelperTest extends TestCase
{
    public function testNumberedAndDatedVersionsAreRead(): void
    {
        $this->assertSame('v2', ApiVersionHelper::fromPath('/api/device/v2/echo'));
        $this->assertSame('v1', ApiVersionHelper::fromPath('/api/v1'));
        $this->assertSame('2026-1', ApiVersionHelper::fromPath('/api/2026-1/session/history/x'));
        $this->assertSame('2025-12', ApiVersionHelper::fromPath('/api/2025-12/'));
    }

    public function testAPathWithoutAVersionReadsNone(): void
    {
        $this->assertNull(ApiVersionHelper::fromPath('/api/session/history/x'));
        $this->assertNull(ApiVersionHelper::fromPath('/api/videos/list'));
        $this->assertNull(ApiVersionHelper::fromPath('/api/report/2026-11-04'));
        $this->assertNull(ApiVersionHelper::fromPath('/api/v2beta/x'));
    }
}
