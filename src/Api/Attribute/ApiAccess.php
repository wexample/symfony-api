<?php

namespace Wexample\SymfonyApi\Api\Attribute;

use Attribute;

/**
 * Who reaches an api route, said where nothing else says it — no
 * `#[IsGranted]`, no access rule:
 *
 * - `ApiAccess::PUBLIC`: anyone, anonymous visitors included — what a page
 *   reads before signing in, a probe;
 * - `ApiAccess::CONTROLLER`: the action decides, in its code — a record's
 *   project read through a scope service, a signature checked —, the reason
 *   given.
 *
 * On a parent class, it holds for every controller built on it — what the
 * parent's code checks for all of them.
 *
 * `ApiRouteAccessTestCaseTrait` reports every api route that declares
 * nothing: an access forgotten, or an access rule that no longer matches its
 * route.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ApiAccess
{
    final public const string PUBLIC = 'public';

    final public const string CONTROLLER = 'controller';

    public function __construct(
        public string $kind,
        public ?string $reason = null,
    ) {
        if (! in_array($kind, [self::PUBLIC, self::CONTROLLER], true)) {
            throw new \InvalidArgumentException(sprintf('Unknown api access "%s": ApiAccess::PUBLIC or ApiAccess::CONTROLLER.', $kind));
        }

        if (self::CONTROLLER === $kind && null === $reason) {
            throw new \InvalidArgumentException('An access checked in the controller says how: ApiAccess::CONTROLLER, "<reason>".');
        }
    }
}
