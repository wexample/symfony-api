<?php

declare(strict_types=1);

namespace Wexample\SymfonyApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyApi\Helper\MachineTokenHelper;
use Wexample\SymfonyApi\Log\ApiTokenLogMasker;
use Wexample\SymfonySecurity\Interface\LogMaskerInterface;

/**
 * No log line keeps a usable API token: one of ours is cut to its hint, any
 * other bearer value or `access_token` parameter is redacted.
 */
class ApiTokenLogMaskerTest extends TestCase
{
    private const string MACHINE_PREFIX = 'mt_';

    private const string USER_PREFIX = 'ut_';

    private ApiTokenLogMasker $masker;

    protected function setUp(): void
    {
        $this->masker = new ApiTokenLogMasker(self::MACHINE_PREFIX, self::USER_PREFIX);
    }

    public function testATokenOfOursIsCutToItsHint(): void
    {
        foreach ([self::MACHINE_PREFIX, self::USER_PREFIX] as $prefix) {
            $token = MachineTokenHelper::generateToken($prefix);
            $masked = $this->masker->mask('Refused token ' . $token . ' from 203.0.113.7');

            $this->assertSame('Refused token ' . MachineTokenHelper::buildHint($token, $prefix) . ' from 203.0.113.7', $masked);
            $this->assertStringNotContainsString(substr($token, strlen($prefix)), $masked);
        }
    }

    public function testABearerOfOursKeepsItsHint(): void
    {
        $token = MachineTokenHelper::generateToken(self::MACHINE_PREFIX);

        $this->assertSame(
            'Authorization: Bearer ' . MachineTokenHelper::buildHint($token, self::MACHINE_PREFIX),
            $this->masker->mask('Authorization: Bearer ' . $token)
        );
    }

    public function testAnyOtherBearerIsRedactedWhole(): void
    {
        $this->assertSame(
            'Authorization: Bearer ' . LogMaskerInterface::REDACTED . ', next',
            $this->masker->mask('Authorization: Bearer eyJhbGciOiJIUzI1NiJ9.e30.sig, next')
        );
        // The scheme is read whatever its case, a short token of ours too.
        $this->assertSame('bearer ' . LogMaskerInterface::REDACTED, $this->masker->mask('bearer mt_short'));
        $this->assertSame('"Bearer ' . LogMaskerInterface::REDACTED . '"', $this->masker->mask('"Bearer abc"'));
    }

    public function testAnAccessTokenParameterIsRedacted(): void
    {
        $token = MachineTokenHelper::generateToken(self::USER_PREFIX);

        foreach (['/api/me?access_token=foreign&page=2', '/api/me?page=2&access_token=' . $token] as $uri) {
            $masked = $this->masker->mask($uri);

            $this->assertStringContainsString('access_token=' . LogMaskerInterface::REDACTED, $masked);
            $this->assertStringNotContainsString('foreign', $masked);
            $this->assertStringNotContainsString(substr($token, strlen(self::USER_PREFIX)), $masked);
            $this->assertStringContainsString('page=2', $masked);
        }
    }

    public function testAPrefixIsReadLiterally(): void
    {
        // A prefix holding a regular expression's characters matches only itself.
        $masker = new ApiTokenLogMasker('a.b+', 'a.b+');
        $token = MachineTokenHelper::generateToken('a.b+');

        $this->assertSame(MachineTokenHelper::buildHint($token, 'a.b+'), $masker->mask($token));
        $this->assertSame('aXbb' . substr($token, 4), $masker->mask('aXbb' . substr($token, 4)));
    }

    public function testTextWithoutATokenIsLeftAlone(): void
    {
        $this->assertSame('GET /api/readings?page=2 200', $this->masker->mask('GET /api/readings?page=2 200'));
    }
}
