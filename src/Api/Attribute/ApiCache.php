<?php

namespace Wexample\SymfonyApi\Api\Attribute;

use Attribute;

/**
 * A read route whose answer can be kept `maxAge` seconds (ApiCacheSubscriber):
 * told to the callers and the caches between them (Cache-Control, ETag, a
 * 304 for an answer they hold), and kept on the server for the calls
 * carrying no token — a token's answer may be its holder's own.
 *
 * `entities` are the classes the answer is read from: one of them written,
 * every answer kept from them is dropped (ApiCacheDoctrineListener), so that
 * an answer is never older than the data, whatever `maxAge` says.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ApiCache
{
    /**
     * @param list<class-string> $entities
     */
    public function __construct(
        public readonly int $maxAge,
        public readonly array $entities = [],
        public readonly bool $server = true,
    ) {
    }
}
