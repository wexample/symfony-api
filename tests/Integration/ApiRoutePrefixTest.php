<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Route;
use Wexample\SymfonyApi\Tests\Fixtures\App\Controller\AccountApiController;
use Wexample\SymfonyApi\Tests\Fixtures\App\Controller\GrantedListApiController;
use Wexample\SymfonyApi\Tests\Traits\TestCase\Application\ApiRoutePrefixTestCaseTrait;

/**
 * The fixture application holds the norm; a route off it is reported.
 */
class ApiRoutePrefixTest extends WebTestCase
{
    use ApiRoutePrefixTestCaseTrait;

    public function testFixtureRoutesFollowTheNorm(): void
    {
        $this->assertApiRoutesFollowPrefixNorm();
    }

    public function testRoutesOffTheNormAreReported(): void
    {
        static::bootKernel();
        $routes = static::getContainer()->get('router')->getRouteCollection();

        // A table's list under /api/, on the session: by an access rule, then by #[IsGranted].
        $routes->add('ruled_list', (new Route('/api/legacy/accounts', ['_controller' => AccountApiController::class . '::account']))->setMethods(['GET']));
        $routes->add('granted_list', (new Route('/api/accounts', ['_controller' => GrantedListApiController::class . '::list']))->setMethods(['GET']));
        $routes->add('elsewhere', (new Route('/lists/accounts', ['_controller' => AccountApiController::class . '::account']))->setMethods(['GET']));

        $problems = $this->findApiRoutePrefixProblems();

        $this->assertSame([
            'ruled_list /api/legacy/accounts: signed in by the session, it is read by the pages and belongs under /_api/.',
            'granted_list /api/accounts: signed in by the session, it is read by the pages and belongs under /_api/.',
            'elsewhere /lists/accounts: an api route lives under /api/ or /_api/.',
        ], $problems);
    }
}
