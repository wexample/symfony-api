<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Route;
use Wexample\SymfonyApi\Api\Attribute\ApiAccess;
use Wexample\SymfonyApi\Tests\Fixtures\App\Controller\AccountApiController;
use Wexample\SymfonyApi\Tests\Fixtures\App\Controller\GrantedListApiController;
use Wexample\SymfonyApi\Tests\Traits\TestCase\Application\ApiRouteAccessTestCaseTrait;

/**
 * Every api route of the package, and of its fixture application, says who
 * reaches it — and one saying nothing is reported.
 */
class ApiRouteAccessTest extends KernelTestCase
{
    use ApiRouteAccessTestCaseTrait;

    public function testEveryRouteDeclaresItsAccess(): void
    {
        self::bootKernel();

        $this->assertApiRoutesDeclareTheirAccess();
    }

    public function testARouteSayingNothingIsReported(): void
    {
        self::bootKernel();
        $routes = static::getContainer()->get('router')->getRouteCollection();
        // No access rule on this path, nothing on the action.
        $routes->add('forgotten', (new Route('/_api/forgotten/list', ['_controller' => AccountApiController::class . '::account']))->setMethods(['GET']));
        // #[IsGranted] on its class.
        $routes->add('granted', (new Route('/_api/granted/list', ['_controller' => GrantedListApiController::class . '::list']))->setMethods(['GET']));

        $problems = $this->findApiRoutesWithoutAccess();

        $this->assertCount(1, $problems);
        $this->assertStringStartsWith('forgotten /_api/forgotten/list', $problems[0]);
    }

    public function testAnAccessCheckedInTheControllerSaysHow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ApiAccess(ApiAccess::CONTROLLER);
    }
}
