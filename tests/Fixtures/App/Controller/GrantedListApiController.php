<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;

/**
 * Not routed: the prefix norm test routes it where it should not be, to see
 * it reported. Its role is asked in the controller, with no access rule.
 */
#[IsGranted('ROLE_USER')]
class GrantedListApiController extends AbstractApiController
{
    public function list(): ApiResponse
    {
        return self::apiResponseSuccess();
    }
}
